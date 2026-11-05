<?php

declare(strict_types=1);

namespace App\Shared\Image;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;

/**
 * URL d'affichage d'une image déposée par un utilisateur (public/uploads/).
 *
 * L'original reste intact sur le disque ; le navigateur reçoit une copie
 * redimensionnée et compressée par LiipImagine (config/packages/liip_imagine.yaml),
 * générée au premier affichage puis servie depuis public/media/cache/.
 *
 * Seules les photos (JPEG, PNG, WebP) de uploads/ sont concernées : les images
 * du thème (AssetMapper), les SVG, les URL externes (avatar Google…) et les
 * pièces jointes du formulaire de contact gardent leur URL normale.
 */
final class UploadedImageUrl
{
    public const DEFAULT_FILTER = 'web';

    public function __construct(
        private readonly CacheManager $cacheManager,
    ) {
    }

    public function supports(?string $path): bool
    {
        if (null === $path || '' === $path) {
            return false;
        }

        $path = ltrim($path, '/');

        return str_starts_with($path, 'uploads/')
            && !str_starts_with($path, 'uploads/contact/')
            && !str_contains($path, '..')
            && 1 === preg_match('/\.(jpe?g|png|webp)$/i', $path);
    }

    /** URL de la copie filtrée ; à n'appeler que si supports() est vrai. */
    public function url(string $path, string $filter = self::DEFAULT_FILTER): string
    {
        return $this->cacheManager->getBrowserPath(ltrim($path, '/'), $filter);
    }

    /** Supprime les copies en cache d'une image (après remplacement ou suppression). */
    public function forget(?string $path): void
    {
        if ($this->supports($path)) {
            $this->cacheManager->remove(ltrim((string) $path, '/'));
        }
    }
}
