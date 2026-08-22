<?php

declare(strict_types=1);

namespace App\Catalog\Repository;

use App\Catalog\Entity\ServiceDetail;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServiceDetail>
 */
class ServiceDetailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceDetail::class);
    }
}
