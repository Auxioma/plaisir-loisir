<?php

declare(strict_types=1);

namespace App\User\Service;

use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Range la photo de profil d'un utilisateur (Lot I, 15/09).
 *
 * OÙ VONT LES FICHIERS
 * Dans `public/uploads/avatars/`, PAS `var/uploads/` comme les pièces
 * justificatives (ProviderDocumentStorage) : une photo de profil est un
 * contenu public par nature, servi directement par le serveur web.
 *
 * MÊME CONTRÔLE QUE ProviderDocumentStorage (§25 du CDC)
 * Type déduit du contenu réel du fichier, taille plafonnée, nom de fichier
 * imprévisible — mais images uniquement (pas de PDF, sans objet ici).
 */
final class AvatarStorageService
{
    /** 4 Mio : une photo de profil n'a aucune raison de dépasser ce poids. */
    public const MAX_BYTES = 4 * 1024 * 1024;

    /** @var list<string> */
    public const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $projectDir,
    ) {
    }

    public function directory(): string
    {
        return $this->projectDir.'/public/uploads/avatars';
    }

    /**
     * @throws \InvalidArgumentException si le fichier est refusé (taille, type)
     */
    public function store(User $user, UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Le fichier n\'a pas pu être reçu. Il est peut-être trop volumineux.');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Votre photo dépasse 4 Mo. Merci de fournir un fichier plus léger.');
        }

        $mimeType = (string) $file->getMimeType();

        if (!\in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException('Le fichier doit être une image (JPEG, PNG ou WebP).');
        }

        $extension = $file->guessExtension() ?: 'bin';
        // bin2hex(random_bytes()) et non le nom d'origine : un nom
        // imprévisible évite les collisions et les noms de fichiers hostiles
        // (même choix que ProviderDocumentStorage).
        $storedName = bin2hex(random_bytes(16)).'.'.$extension;

        $directory = $this->directory();

        if (!is_dir($directory) && !mkdir($directory, 0o755, true) && !is_dir($directory)) {
            throw new FileException(\sprintf('Impossible de créer le dossier de dépôt « %s ».', $directory));
        }

        $previous = $user->getAvatarPath();

        $file->move($directory, $storedName);
        $user->setAvatarPath('uploads/avatars/'.$storedName);

        $this->entityManager->flush();

        // Une seule photo à la fois : l'ancienne n'a plus lieu d'être conservée.
        if (null !== $previous) {
            $this->deleteFile($previous);
        }
    }

    /** Retire la photo de profil (retour à la silhouette par défaut). */
    public function remove(User $user): void
    {
        $previous = $user->getAvatarPath();
        $user->setAvatarPath(null);
        $this->entityManager->flush();

        if (null !== $previous) {
            $this->deleteFile($previous);
        }
    }

    private function deleteFile(string $relativePath): void
    {
        $path = $this->projectDir.'/public/'.$relativePath;

        if (is_file($path)) {
            @unlink($path);
        }
    }
}
