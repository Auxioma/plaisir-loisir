<?php

declare(strict_types=1);

namespace App\Review\Repository;

use App\Booking\Entity\Booking;
use App\Catalog\Entity\Service;
use App\Review\Entity\Review;
use App\Review\Enum\ReviewStatus;
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

    public function countPublishedForService(Service $service): int
    {
        return $this->count(['service' => $service, 'status' => ReviewStatus::Published]);
    }

    public function averageRatingForService(Service $service): ?float
    {
        $average = $this->createQueryBuilder('r')
            ->select('AVG(r.rating)')
            ->andWhere('r.service = :service')
            ->andWhere('r.status = :published')
            ->setParameter('service', $service)
            ->setParameter('published', ReviewStatus::Published)
            ->getQuery()
            ->getSingleScalarResult();

        return null === $average ? null : (float) $average;
    }
}
