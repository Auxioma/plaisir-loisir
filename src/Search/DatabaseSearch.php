<?php

declare(strict_types=1);

namespace App\Search;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\Destination;
use App\Catalog\Repository\DestinationRepository;
use App\Catalog\Repository\ServiceRepository;

/**
 * Implémentation de la recherche adossée à PostgreSQL (source de vérité).
 * Sera doublée plus tard par une implémentation Elasticsearch derrière la même interface.
 */
final class DatabaseSearch implements SearchService
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly DestinationRepository $destinations,
    ) {
    }

    public function searchActivities(string $query, ?Category $category = null, ?Destination $destination = null, int $limit = 20): array
    {
        return $this->services->searchPublished($query, $category, $destination, $limit);
    }

    public function searchDestinations(string $query, int $limit = 20): array
    {
        return $this->destinations->searchByName($query, $limit);
    }
}
