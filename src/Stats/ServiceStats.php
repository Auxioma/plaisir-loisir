<?php

declare(strict_types=1);

namespace App\Stats;

/**
 * Statistiques agrégées d'une activité (pour le tableau de bord annonceur).
 */
final class ServiceStats
{
    public function __construct(
        public readonly int $reviewsCount,
        public readonly ?float $averageRating,
        public readonly int $bookingsCount,
    ) {
    }
}
