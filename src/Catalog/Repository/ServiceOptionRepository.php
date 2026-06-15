<?php

declare(strict_types=1);

namespace App\Catalog\Repository;

use App\Catalog\Entity\ServiceOption;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServiceOption>
 */
class ServiceOptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceOption::class);
    }
}
