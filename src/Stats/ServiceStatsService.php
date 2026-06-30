<?php

declare(strict_types=1);

namespace App\Stats;

use App\Booking\Repository\BookingRepository;
use App\Catalog\Entity\Service;
use App\Review\Repository\ReviewRepository;

/**
 * Calcule les statistiques d'une activité (note moyenne, nombre d'avis et de
 * réservations) pour le tableau de bord annonceur.
 */
final class ServiceStatsService
{
    public function __construct(
        private readonly ReviewRepository $reviews,
        private readonly BookingRepository $bookings,
    ) {
    }

    public function forService(Service $service): ServiceStats
    {
        return new ServiceStats(
            $this->reviews->countPublishedForService($service),
            $this->reviews->averageRatingForService($service),
            $this->bookings->countForService($service),
        );
    }
}
