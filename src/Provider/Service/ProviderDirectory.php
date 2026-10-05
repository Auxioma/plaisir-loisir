<?php

declare(strict_types=1);

namespace App\Provider\Service;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\Service;
use App\Catalog\Enum\ServiceStatus;
use App\Catalog\Presenter\ActivityPresenter;
use App\Catalog\Repository\ServiceRepository;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderStatus;
use App\Provider\Geo\FrenchCityCoordinates;
use App\Provider\Geo\HaversineDistance;
use App\Provider\Repository\ProviderProfileRepository;

/**
 * Annuaire « Trouver un professionnel » (refonte du 05/10).
 *
 * Chaque prestataire vérifié est présenté avec ce que la plateforme sait de
 * lui : ses activités PUBLIÉES, la note moyenne pondérée par le nombre
 * d'avis de ces activités, le prix d'appel, ses catégories. Les filtres
 * portent sur ces mêmes données : un professionnel ressort pour une
 * catégorie s'il y propose au moins une activité, pas seulement si c'est sa
 * catégorie principale déclarée.
 */
final class ProviderDirectory
{
    public const SORTS = ['pertinence' => 'Les plus recommandés', 'note' => 'Mieux notés', 'prix' => 'Prix croissant', 'activites' => 'Plus d’activités', 'nom' => 'Nom (A-Z)'];

    public function __construct(
        private readonly ProviderProfileRepository $providers,
        private readonly ServiceRepository $services,
        private readonly ActivityPresenter $presenter,
    ) {
    }

    /**
     * @param array{q?: string, category?: ?Category, city?: string, radius?: int, rating?: float, sort?: string} $f
     *
     * @return list<array<string, mixed>>
     */
    public function search(array $f): array
    {
        $rows = [];
        $origin = '' !== ($f['city'] ?? '') && ($f['radius'] ?? 0) > 0 ? FrenchCityCoordinates::coordinatesFor($f['city']) : null;

        foreach ($this->providers->findBy(['status' => ProviderStatus::Verified]) as $provider) {
            $row = $this->card($provider);

            if (null !== ($f['category'] ?? null)) {
                $slug = $f['category']->getSlug();
                if ($provider->getMainCategory()?->getSlug() !== $slug && !\in_array($slug, $row['categorySlugs'], true)) {
                    continue;
                }
            }
            if ('' !== ($q = mb_strtolower(trim($f['q'] ?? '')))) {
                $haystack = mb_strtolower($provider->getDisplayName().' '.$provider->getCompanyName().' '.$provider->getBio().' '.implode(' ', array_column($row['activities'], 'title')));
                if (!str_contains($haystack, $q)) {
                    continue;
                }
            }
            if ('' !== ($f['city'] ?? '')) {
                if (null !== $origin) {
                    if (null === $row['lat']) {
                        continue;
                    }
                    $row['distance'] = (int) round(HaversineDistance::betweenKm($origin[0], $origin[1], $row['lat'], $row['lng']));
                    if ($row['distance'] > $f['radius']) {
                        continue;
                    }
                } else {
                    $city = mb_strtolower(trim($f['city']));
                    $places = mb_strtolower($provider->getCity().' '.$provider->getInterventionZone().' '.implode(' ', array_column($row['activities'], 'place')));
                    if (!str_contains($places, $city)) {
                        continue;
                    }
                }
            }
            if (($f['rating'] ?? 0) > 0 && ($row['rating'] ?? 0) < $f['rating']) {
                continue;
            }
            $rows[] = $row;
        }

        usort($rows, match ($f['sort'] ?? 'pertinence') {
            'note' => static fn (array $a, array $b): int => [$b['rating'] ?? 0, $b['reviews']] <=> [$a['rating'] ?? 0, $a['reviews']],
            'prix' => static fn (array $a, array $b): int => ($a['priceFrom'] ?? \PHP_FLOAT_MAX) <=> ($b['priceFrom'] ?? \PHP_FLOAT_MAX),
            'activites' => static fn (array $a, array $b): int => \count($b['activities']) <=> \count($a['activities']),
            'nom' => static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']),
            default => null !== $origin
                ? static fn (array $a, array $b): int => ($a['distance'] ?? 0) <=> ($b['distance'] ?? 0)
                : static fn (array $a, array $b): int => [$b['reviews'], $b['rating'] ?? 0] <=> [$a['reviews'], $a['rating'] ?? 0],
        });

        return $rows;
    }

    /**
     * Le prestataire et ses chiffres réels.
     *
     * @return array<string, mixed>
     */
    public function card(ProviderProfile $provider): array
    {
        $published = array_values(array_filter(
            $this->services->findForProvider($provider),
            static fn (Service $s): bool => ServiceStatus::Published === $s->getStatus(),
        ));
        $activities = $this->presenter->cards($published, true);

        $reviews = 0;
        $weighted = 0.0;
        $prices = [];
        $categories = [];
        $lat = null;
        $lng = null;
        foreach ($published as $i => $service) {
            $count = $service->getReviewsCount();
            if ($count > 0 && null !== $service->getRatingAverage()) {
                $reviews += $count;
                $weighted += (float) $service->getRatingAverage() * $count;
            }
            if (null !== $activities[$i]['price']) {
                $prices[] = (float) $activities[$i]['price'];
            }
            if (null !== $service->getCategory()) {
                $categories[$service->getCategory()->getSlug()] = $service->getCategory()->getName();
            }
            if (null === $lat && null !== $service->getLatitude()) {
                $lat = (float) $service->getLatitude();
                $lng = (float) $service->getLongitude();
            }
        }
        if (null === $lat && null !== ($coords = FrenchCityCoordinates::coordinatesFor($provider->getCity()))) {
            [$lat, $lng] = $coords;
        }

        return [
            'provider' => $provider,
            'slug' => $provider->getSlug(),
            'name' => $provider->getDisplayName(),
            'avatar' => $provider->getUser()?->getAvatarPath(),
            'cover' => $provider->getCoverPath() ?? ($activities[0]['image'] ?? null),
            'category' => $provider->getMainCategory()?->getName() ?? (reset($categories) ?: null),
            'categorySlugs' => array_keys($categories),
            'categories' => array_values($categories),
            'city' => $provider->getCity() ?? ($activities[0]['place'] ?? null),
            'activities' => $activities,
            'reviews' => $reviews,
            'rating' => $reviews > 0 ? round($weighted / $reviews, 1) : null,
            'priceFrom' => [] !== $prices ? min($prices) : null,
            'lat' => $lat,
            'lng' => $lng,
            'distance' => null,
        ];
    }
}
