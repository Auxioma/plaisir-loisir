<?php

declare(strict_types=1);

namespace App\Review\Repository;

use App\Booking\Entity\Booking;
use App\Catalog\Entity\Service;
use App\Review\Entity\Review;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    public function findOneByBooking(Booking $booking): ?Review
    {
        return $this->findOneBy(['booking' => $booking]);
    }

    /**
     * @return Review[]
     */
    public function findByService(Service $service): array
    {
        return $this->findBy(['service' => $service], ['createdAt' => 'DESC']);
    }
}
