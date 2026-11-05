<?php

declare(strict_types=1);

namespace App\Shared\Twig;

use App\Shared\Image\UploadedImageUrl;
use Symfony\Component\Asset\Packages;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `image_url(chemin, 'card')` : comme asset(), mais une photo déposée
 * (uploads/) est servie au format du filtre demandé (card, avatar, web).
 */
final class ImageExtension extends AbstractExtension
{
    public function __construct(
        private readonly UploadedImageUrl $images,
        private readonly Packages $packages,
    ) {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('image_url', $this->imageUrl(...))];
    }

    public function imageUrl(?string $path, string $filter = UploadedImageUrl::DEFAULT_FILTER): string
    {
        if ($this->images->supports($path)) {
            return $this->images->url((string) $path, $filter);
        }

        return $this->packages->getUrl((string) $path);
    }
}
