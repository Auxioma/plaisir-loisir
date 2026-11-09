<?php

declare(strict_types=1);

namespace App\Catalog\Service;

use App\Catalog\Entity\Destination;
use App\Catalog\Repository\DestinationRepository;
use App\Catalog\Repository\ServiceRepository;

/**
 * Blocs de mise en avant du catalogue, calculés depuis la base (06/11).
 *
 * Ils venaient de StaticCatalog / StaticDestinations : des villes (Lille,
 * Paris…), des compteurs (« 88 activités ») et des idées (« Les Alpes
 * françaises ») recopiés de la maquette, identiques sur toutes les pages et
 * sans rapport avec le catalogue réel. Chaque bloc montre désormais ce que la
 * base contient, et chaque élément mène à des résultats.
 */
final class CatalogShowcase
{
    /** Photo de repli d'une catégorie sans activité illustrée. */
    private const FALLBACK_IMAGE = 'images/activities/canoe-riviere.jpg';

    /** @var list<array{destination: Destination, total: int}>|null */
    private ?array $destinationCounts = null;

    public function __construct(
        private readonly ServiceRepository $services,
        private readonly DestinationRepository $destinations,
    ) {
    }

    /**
     * « Votre sélection d'activités » : les catégories les plus fournies,
     * avec leur nombre réel d'activités publiées.
     *
     * @return list<array{title: string, slug: string, count: int, image: string}>
     */
    public function selections(int $limit = 4): array
    {
        $counts = $this->services->countPublishedByCategory($limit);
        $covers = $this->services->coverByCategory(array_map(static fn (array $c): string => $c['category']->getSlug(), $counts));

        return array_map(static fn (array $c): array => [
            'title' => $c['category']->getName(),
            'slug' => $c['category']->getSlug(),
            'count' => $c['total'],
            'image' => $covers[$c['category']->getSlug()] ?? self::FALLBACK_IMAGE,
        ], $counts);
    }

    /**
     * Les destinations qui proposent au moins une activité, les plus
     * fournies d'abord.
     *
     * @return list<array{name: string, slug: string, count: int}>
     */
    public function cities(int $limit = 12): array
    {
        $this->destinationCounts ??= $this->services->countPublishedByDestination(50);

        return array_map(static fn (array $d): array => [
            'name' => $d['destination']->getName(),
            'slug' => $d['destination']->getSlug(),
            'count' => $d['total'],
        ], \array_slice($this->destinationCounts, 0, $limit));
    }

    /**
     * « Idées du moment » : les destinations les mieux notées qui ont une
     * photo, à défaut les premières de la liste.
     *
     * @return list<array{title: string, subtitle: string|null, slug: string, image: string}>
     */
    public function ideas(int $limit = 3): array
    {
        $all = array_values(array_filter(
            $this->destinations->findForListing(),
            static fn (Destination $d): bool => null !== $d->getHeroImage(),
        ));

        usort($all, static fn (Destination $a, Destination $b): int => [(float) $b->getRatingAverage(), $b->getReviewsCount()] <=> [(float) $a->getRatingAverage(), $a->getReviewsCount()]);

        return array_map(static fn (Destination $d): array => [
            'title' => $d->getName(),
            'subtitle' => $d->getTagline() ?? $d->getRegion(),
            'slug' => $d->getSlug(),
            'image' => (string) $d->getHeroImage(),
        ], \array_slice($all, 0, $limit));
    }
}
