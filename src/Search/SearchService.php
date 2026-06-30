<?php

declare(strict_types=1);

namespace App\Search;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\Destination;
use App\Catalog\Entity\Service;

/**
 * Abstraction de la recherche. On code contre cette interface ; l'implémentation
 * PostgreSQL (DatabaseSearch) pourra être remplacée par Elasticsearch sans toucher
 * au reste du code.
 */
interface SearchService
{
    /**
     * @return Service[]
     */
    public function searchActivities(string $query, ?Category $category = null, ?Destination $destination = null, int $limit = 20): array;

    /**
     * @return Destination[]
     */
    public function searchDestinations(string $query, int $limit = 20): array;
}
