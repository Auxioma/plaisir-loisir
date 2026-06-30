<?php

declare(strict_types=1);

namespace App\Quote\Repository;

use App\Quote\Entity\ServiceRequest;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServiceRequest>
 */
class ServiceRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceRequest::class);
    }

    /**
     * @return ServiceRequest[]
     */
    public function findByClient(User $client): array
    {
        return $this->findBy(['client' => $client], ['createdAt' => 'DESC']);
    }
}
