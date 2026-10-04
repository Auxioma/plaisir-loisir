<?php

declare(strict_types=1);

namespace App\Provider\Twig;

use App\Catalog\Entity\Service;
use App\Provider\Service\ProviderSpace;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Filtres de l'espace pro : vignette et prix d'une activité.
 */
final class ProviderSpaceExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('pp_cover', static fn (?Service $service): string => ProviderSpace::cover($service)),
            new TwigFilter('pp_price', static fn (Service $service): ?float => ProviderSpace::price($service)),
        ];
    }
}
