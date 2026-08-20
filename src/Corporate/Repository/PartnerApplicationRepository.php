<?php

declare(strict_types=1);

namespace App\Corporate\Repository;

use App\Corporate\Entity\PartnerApplication;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PartnerApplication>
 */
class PartnerApplicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PartnerApplication::class);
    }

    /**
     * Les demandes non encore prises en charge, la plus ancienne d'abord.
     *
     * @return list<PartnerApplication>
     */
    public function findPending(int $limit = 50): array
    {
        /** @var list<PartnerApplication> $results */
        $results = $this->createQueryBuilder('m')
            ->andWhere('m.handledAt IS NULL')
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $results;
    }
}
