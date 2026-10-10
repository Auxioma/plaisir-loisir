<?php

declare(strict_types=1);

namespace App\Corporate\Repository;

use App\Corporate\Entity\CompanyContact;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompanyContact>
 */
class CompanyContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompanyContact::class);
    }

    public function findCurrent(): ?CompanyContact
    {
        return $this->findOneBy([], ['createdAt' => 'ASC']);
    }
}
