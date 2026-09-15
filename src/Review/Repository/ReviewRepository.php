<?php

declare(strict_types=1);

namespace App\Review\Repository;

use App\Provider\Entity\ProviderProfile;
use App\Quote\Entity\Quote;
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

    public function findOneByQuote(Quote $quote): ?Review
    {
        return $this->findOneBy(['quote' => $quote]);
    }

    /**
     * @return Review[]
     */
    public function findForProvider(ProviderProfile $provider): array
    {
        return $this->findBy(['provider' => $provider], ['createdAt' => 'DESC']);
    }

    public function countPublishedForProvider(ProviderProfile $provider): int
    {
        return $this->count(['provider' => $provider, 'status' => ReviewStatus::Published]);
    }

    public function averageRatingForProvider(ProviderProfile $provider): ?float
    {
        $average = $this->createQueryBuilder('r')
            ->select('AVG(r.rating)')
            ->andWhere('r.provider = :provider')
            ->andWhere('r.status = :published')
            ->setParameter('provider', $provider->getId(), 'ulid')
            ->setParameter('published', ReviewStatus::Published)
            ->getQuery()
            ->getSingleScalarResult();

        return null === $average ? null : (float) $average;
    }
}
