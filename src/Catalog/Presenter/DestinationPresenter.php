<?php

declare(strict_types=1);

namespace App\Catalog\Presenter;

use App\Catalog\Entity\Destination;

/**
 * Traduit une entité Destination en la forme de tableau que les gabarits
 * attendent, exactement comme ActivityPresenter le fait pour les activités.
 *
 * Forme produite, identique à StaticDestinations::popular() :
 * name, tagline, rating, reviews, count, price, badge, favorite, image.
 */
final class DestinationPresenter
{
    /**
     * Même repli que pour les activités : une destination sans photo ne doit
     * pas faire tomber la page, `asset(null)` lève une exception.
     */
    private const FALLBACK_IMAGE = 'images/destinations/banniere-monde.jpg';

    /**
     * @param list<string> $favoriteSlugs destinations mises en favori par le
     *                                    visiteur en cours
     *
     * @return array<string, mixed>
     */
    public function card(Destination $destination, array $favoriteSlugs = []): array
    {
        return [
            'name' => $destination->getName(),
            'tagline' => $destination->getTagline(),
            'rating' => $this->rating($destination),
            'reviews' => $destination->getReviewsCount(),
            'count' => $destination->getActivitiesCount(),
            'price' => $destination->getPriceFrom(),
            'badge' => $destination->getBadge(),
            'favorite' => \in_array($destination->getSlug(), $favoriteSlugs, true),
            // Le slug ne s'affiche pas : il sert au coeur des favoris a dire
            // au serveur de quelle destination il parle.
            'slug' => $destination->getSlug(),
            'image' => $destination->getHeroImage() ?? self::FALLBACK_IMAGE,
        ];
    }

    /**
     * @param iterable<Destination> $destinations
     * @param list<string>          $favoriteSlugs
     *
     * @return list<array<string, mixed>>
     */
    public function cards(iterable $destinations, array $favoriteSlugs = []): array
    {
        $cards = [];

        foreach ($destinations as $destination) {
            $cards[] = $this->card($destination, $favoriteSlugs);
        }

        return $cards;
    }

    /**
     * Note affichée avec une seule décimale : la colonne en stocke deux, et
     * « 4.80 » n'est pas ce qu'écrit la maquette.
     */
    private function rating(Destination $destination): ?string
    {
        $average = $destination->getRatingAverage();

        return null !== $average ? number_format((float) $average, 1, '.', '') : null;
    }
}
