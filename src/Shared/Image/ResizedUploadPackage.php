<?php

declare(strict_types=1);

namespace App\Shared\Image;

use Symfony\Component\Asset\PackageInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * `asset('uploads/….jpg')` sert la copie compressée (filtre « web ») au lieu
 * de l'original, partout sans toucher aux gabarits : pages, e-mails, partage
 * (og:image). Vignettes et avatars : `image_url(chemin, 'card'|'avatar')`.
 */
#[AsDecorator('assets._default_package')]
final class ResizedUploadPackage implements PackageInterface
{
    public function __construct(
        #[AutowireDecorated]
        private readonly PackageInterface $inner,
        private readonly UploadedImageUrl $images,
    ) {
    }

    public function getVersion(string $path): string
    {
        return $this->inner->getVersion($path);
    }

    public function getUrl(string $path): string
    {
        return $this->images->supports($path) ? $this->images->url($path) : $this->inner->getUrl($path);
    }
}
