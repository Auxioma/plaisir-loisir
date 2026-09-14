<?php

declare(strict_types=1);

namespace App\Quote\Repository;

use App\Catalog\Entity\Category;
use App\Quote\Entity\ServiceRequest;
use App\Quote\Enum\ServiceRequestStatus;
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

    /**
     * Demandes ouvertes dans le métier d'un prestataire — sa « boîte de
     * réception » (§10 du CDC). Ouvertes uniquement : une demande close a déjà
     * trouvé son prestataire, inutile de la lui montrer.
     *
     * @return list<ServiceRequest>
     */
    public function findOpenForCategory(Category $category): array
    {
        /** @var list<ServiceRequest> $results */
        $results = $this->createQueryBuilder('r')
            ->andWhere('r.category = :category')
            ->andWhere('r.status = :status')
            ->setParameter('category', $category->getId(), 'ulid')
            ->setParameter('status', ServiceRequestStatus::Open)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $results;
    }
}
