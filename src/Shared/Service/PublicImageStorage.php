<?php

declare(strict_types=1);

namespace App\Shared\Service;

use App\Shared\Image\UploadedImageUrl;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Dépôt d'images publiques (photos d'activités, couverture de fiche pro)
 * sous public/uploads/<dossier>/, avec les mêmes garde-fous que
 * AvatarStorageService : type réel du fichier, nom imprévisible. Pas de
 * plafond de poids côté application : seul celui de PHP (upload_max_filesize)
 * s'applique ; l'original est gardé, LiipImagine sert des copies compressées.
 */
final class PublicImageStorage
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly UploadedImageUrl $images,
    ) {
    }

    /**
     * @return string chemin relatif à public/ (ex. « uploads/activities/ab12….jpg »)
     *
     * @throws \InvalidArgumentException si le fichier est refusé
     */
    public function store(UploadedFile $file, string $folder): string
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Le fichier n\'a pas pu être reçu. Il est peut-être trop volumineux.');
        }

        if (!\in_array((string) $file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException('Le fichier doit être une image (JPEG, PNG ou WebP).');
        }

        $folder = trim(preg_replace('/[^a-z0-9_-]/', '', strtolower($folder)) ?? 'images', '/');
        $directory = $this->projectDir.'/public/uploads/'.$folder;
        if (!is_dir($directory) && !mkdir($directory, 0o755, true) && !is_dir($directory)) {
            throw new FileException(\sprintf('Impossible de créer le dossier de dépôt « %s ».', $directory));
        }

        $name = bin2hex(random_bytes(16)).'.'.($file->guessExtension() ?: 'jpg');
        $file->move($directory, $name);

        return 'uploads/'.$folder.'/'.$name;
    }

    /** Supprime un fichier précédemment déposé (ignore les images du thème). */
    public function delete(?string $relativePath): void
    {
        if (null === $relativePath || !str_starts_with($relativePath, 'uploads/')) {
            return;
        }

        $this->images->forget($relativePath);
        $path = $this->projectDir.'/public/'.$relativePath;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
