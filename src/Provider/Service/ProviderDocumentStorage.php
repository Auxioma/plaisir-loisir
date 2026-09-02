<?php

declare(strict_types=1);

namespace App\Provider\Service;

use App\Provider\Entity\ProviderDocument;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderDocumentKind;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Range les pièces justificatives d'un professionnel et en garde la trace.
 *
 * OÙ VONT LES FICHIERS
 * Dans `var/uploads/provider-documents/`, PAS dans `public/uploads/` où
 * atterrissent les photos du catalogue. Un extrait Kbis, un certificat
 * d'assurance ou une licence d'exploitation n'ont aucune raison d'être servis
 * par le serveur web : sous `public/`, ils seraient téléchargeables par
 * quiconque connaît — ou devine — leur adresse. Ils sont donc hors racine, et
 * seuls le back-office et le futur espace pro les rendront, après contrôle
 * des droits.
 *
 * CE QUI EST REFUSÉ
 * Tout ce qui n'est pas un PDF ou une image : un document administratif se
 * dépose sous l'une de ces deux formes, et accepter le reste reviendrait à
 * héberger des exécutables. Le type est déduit du contenu réel du fichier
 * (getMimeType() lit les premiers octets), pas de l'extension ni de l'en-tête
 * envoyé par le navigateur, qui se falsifient tous deux en une ligne.
 */
final class ProviderDocumentStorage
{
    /** 8 Mio : au-delà, c'est un scan mal réglé, pas un justificatif. */
    public const MAX_BYTES = 8 * 1024 * 1024;

    /** @var list<string> */
    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/heic',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $projectDir,
    ) {
    }

    public function directory(): string
    {
        return $this->projectDir.'/var/uploads/provider-documents';
    }

    /**
     * Chemin absolu d'une pièce déjà rangée.
     */
    public function pathOf(ProviderDocument $document): string
    {
        return $this->directory().'/'.$document->getStoredName();
    }

    /**
     * Range un fichier reçu et renvoie la pièce enregistrée.
     *
     * @throws \InvalidArgumentException si le fichier est refusé (taille, type)
     */
    public function store(ProviderProfile $profile, UploadedFile $file, ProviderDocumentKind $kind): ProviderDocument
    {
        if (!$file->isValid()) {
            // Cas le plus fréquent : le fichier dépasse upload_max_filesize et
            // PHP l'a tronqué en silence. Sans ce garde-fou, on enregistrait
            // une ligne pointant vers un fichier inexistant.
            throw new \InvalidArgumentException(\sprintf('Le fichier « %s » n\'a pas pu être reçu. Il est peut-être trop volumineux.', $file->getClientOriginalName()));
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new \InvalidArgumentException(\sprintf('« %s » dépasse 8 Mo. Merci de fournir un fichier plus léger.', $file->getClientOriginalName()));
        }

        $mimeType = (string) $file->getMimeType();

        if (!\in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException(\sprintf('« %s » n\'est ni un PDF ni une image. Merci de déposer un document lisible dans l\'un de ces formats.', $file->getClientOriginalName()));
        }

        $extension = $file->guessExtension() ?: 'bin';
        // bin2hex(random_bytes()) et non le nom d'origine : un nom imprévisible
        // interdit d'énumérer les dossiers des autres prestataires, et évite
        // du même coup les collisions et les noms de fichiers hostiles.
        $storedName = bin2hex(random_bytes(16)).'.'.$extension;

        $directory = $this->directory();

        if (!is_dir($directory) && !mkdir($directory, 0o770, true) && !is_dir($directory)) {
            throw new FileException(\sprintf('Impossible de créer le dossier de dépôt « %s ».', $directory));
        }

        $file->move($directory, $storedName);

        $document = (new ProviderDocument())
            ->setProviderProfile($profile)
            ->setKind($kind)
            ->setOriginalName($file->getClientOriginalName())
            ->setStoredName($storedName)
            ->setMimeType($mimeType)
            ->setSizeBytes((int) filesize($directory.'/'.$storedName));

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        return $document;
    }
}
