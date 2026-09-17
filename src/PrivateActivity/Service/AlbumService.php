<?php

declare(strict_types=1);

namespace App\PrivateActivity\Service;

use App\PrivateActivity\Entity\Album;
use App\PrivateActivity\Entity\Photo;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Repository\AlbumRepository;
use App\PrivateActivity\Repository\PhotoRepository;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Album photo d'une activité privée (§4 de docs/corrections-client-2026-07-27.md).
 *
 * MÊME CONTRÔLE QUE AvatarStorageService (§25 du CDC)
 * Type déduit du contenu réel du fichier, taille plafonnée, nom de fichier
 * imprévisible, dans `public/uploads/` : un contenu public par nature, servi
 * directement par le serveur web — pas `var/uploads/` (réservé aux pièces
 * justificatives, privées, cf. ProviderDocumentStorage).
 *
 * PAS D'ÉCRAN FIGMA POUR CET ÉCRAN (statut « à maquetter » du document ci-
 * dessus) : ce service et son contrôleur sont fonctionnels, l'UI reste
 * volontairement sommaire en attendant la maquette.
 */
final class AlbumService
{
    /** §4 de docs/corrections-client-2026-07-27.md. */
    public const MAX_PHOTOS_PER_ALBUM = 25;

    /** 4 Mio : même plafond que la photo de profil (AvatarStorageService). */
    public const MAX_BYTES = 4 * 1024 * 1024;

    /** @var list<string> */
    public const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AlbumRepository $albums,
        private readonly PhotoRepository $photos,
        private readonly string $projectDir,
    ) {
    }

    public function directory(): string
    {
        return $this->projectDir.'/public/uploads/albums';
    }

    /**
     * Crée l'album au premier dépôt : la plupart des activités n'en auront
     * jamais besoin, pas la peine d'en créer un à chaque activité publiée.
     */
    public function getOrCreate(PrivateActivity $activity): Album
    {
        $album = $this->albums->findOneByActivity($activity);

        if (null !== $album) {
            return $album;
        }

        $album = (new Album())->setPrivateActivity($activity);
        $this->entityManager->persist($album);
        $this->entityManager->flush();

        return $album;
    }

    /**
     * @throws \InvalidArgumentException si le fichier est refusé (taille, type) ou l'album complet
     */
    public function addPhoto(Album $album, User $author, UploadedFile $file): Photo
    {
        if ($this->photos->countForAlbum($album) >= self::MAX_PHOTOS_PER_ALBUM) {
            throw new \InvalidArgumentException(\sprintf('Cet album a atteint sa limite de %d photos.', self::MAX_PHOTOS_PER_ALBUM));
        }

        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Le fichier n\'a pas pu être reçu. Il est peut-être trop volumineux.');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Cette photo dépasse 4 Mo. Merci de fournir un fichier plus léger.');
        }

        $mimeType = (string) $file->getMimeType();

        if (!\in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException('Le fichier doit être une image (JPEG, PNG ou WebP).');
        }

        $extension = $file->guessExtension() ?: 'bin';
        $storedName = bin2hex(random_bytes(16)).'.'.$extension;

        $directory = $this->directory();

        if (!is_dir($directory) && !mkdir($directory, 0o755, true) && !is_dir($directory)) {
            throw new FileException(\sprintf('Impossible de créer le dossier de dépôt « %s ».', $directory));
        }

        $file->move($directory, $storedName);

        $photo = (new Photo())
            ->setAlbum($album)
            ->setAuthor($author)
            ->setPath('uploads/albums/'.$storedName);

        $this->entityManager->persist($photo);
        $this->entityManager->flush();

        return $photo;
    }

    /**
     * Réservée à l'auteur de la photo ou à l'organisateur de l'activité —
     * vérifié par l'appelant (AlbumController), pas ici : ce service ne
     * connaît pas les droits, seulement le stockage (même séparation que
     * PrivateActivityService).
     */
    public function removePhoto(Photo $photo): void
    {
        $this->deleteFile($photo->getPath());
        $this->entityManager->remove($photo);
        $this->entityManager->flush();
    }

    private function deleteFile(string $relativePath): void
    {
        $path = $this->projectDir.'/public/'.$relativePath;

        if (is_file($path)) {
            @unlink($path);
        }
    }
}
