<?php

declare(strict_types=1);

namespace App\Quote\Repository;

use App\Catalog\Entity\Category;
use App\Provider\Entity\ProviderProfile;
use App\Quote\Entity\Quote;
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
     * Demandes ouvertes dans l'une de ces catégories (05/10) : celle du
     * professionnel et celles de ses activités publiées.
     *
     * @param list<Category> $categories
     *
     * @return list<ServiceRequest>
     */
    public function findOpenForCategories(array $categories): array
    {
        if ([] === $categories) {
            return [];
        }

        /** @var list<ServiceRequest> $results */
        $results = $this->createQueryBuilder('r')
            ->andWhere('r.category IN (:categories)')
            ->andWhere('r.status = :status')
            ->setParameter('categories', array_values(array_filter(array_map(static fn (Category $c): ?string => $c->getId()?->toRfc4122(), $categories))), \Doctrine\DBAL\ArrayParameterType::STRING)
            ->setParameter('status', ServiceRequestStatus::Open)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $results;
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

    /**
     * Compteur du badge « Demandes reçues » (menu compte pro + raccourci du
     * header) : demandes ouvertes dans le métier du prestataire qu'il n'a PAS
     * encore devisées — un devis déjà envoyé n'a plus rien de « nouveau ».
     * Une seule requête (`NOT EXISTS`), contrairement à
     * `ProviderRequestController::alreadyQuotedIds` qui interroge une fois
     * par demande : cette page-là affiche le détail de chaque ligne et ne
     * peut pas s'en passer, ce compteur n'a besoin que du total.
     */
    public function countOpenUnquotedForProvider(Category $category, ProviderProfile $provider): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.category = :category')
            ->andWhere('r.status = :status')
            ->andWhere('NOT EXISTS (
                SELECT 1 FROM '.Quote::class.' q
                WHERE q.serviceRequest = r AND q.provider = :provider
            )')
            ->setParameter('category', $category->getId(), 'ulid')
            ->setParameter('status', ServiceRequestStatus::Open)
            ->setParameter('provider', $provider->getId(), 'ulid')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
