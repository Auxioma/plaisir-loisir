<?php

declare(strict_types=1);

namespace App\Booking\Repository;

use App\Booking\Entity\Booking;
use App\Catalog\Entity\Service;
use App\Provider\Entity\ProviderProfile;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    /**
     * @return Booking[]
     */
    public function findByClient(User $client): array
    {
        return $this->findBy(['client' => $client], ['createdAt' => 'DESC']);
    }

    public function countForService(Service $service): int
    {
        return $this->count(['service' => $service]);
    }

    /**
     * Toutes les réservations reçues par un professionnel (espace pro),
     * avec client et activité chargés d'un coup.
     *
     * @return list<Booking>
     */
    public function findForProvider(ProviderProfile $provider): array
    {
        return $this->createQueryBuilder('b')
            ->innerJoin('b.service', 's')->addSelect('s')
            ->innerJoin('b.client', 'c')->addSelect('c')
            ->andWhere('s.provider = :provider')
            ->andWhere('b.deletedAt IS NULL')
            ->setParameter('provider', $provider->getId(), 'ulid')
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
