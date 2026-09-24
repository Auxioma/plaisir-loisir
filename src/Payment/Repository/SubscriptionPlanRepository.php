<?php

declare(strict_types=1);

namespace App\Payment\Repository;

use App\Payment\Entity\SubscriptionPlan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SubscriptionPlan>
 */
class SubscriptionPlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubscriptionPlan::class);
    }

    /**
     * @return list<SubscriptionPlan>
     */
    public function findActive(): array
    {
        /** @var list<SubscriptionPlan> $results */
        $results = $this->findBy(['active' => true], ['position' => 'ASC', 'priceAmount' => 'ASC']);

        return $results;
    }
}
