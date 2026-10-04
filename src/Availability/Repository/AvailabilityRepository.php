<?php

declare(strict_types=1);

namespace App\Availability\Repository;

use App\Availability\Entity\Availability;
use App\Catalog\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Availability>
 */
class AvailabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Availability::class);
    }

    /**
     * Créneaux à venir d'une activité (à partir de l'instant fourni), triés.
     *
     * @return Availability[]
     */
    public function findUpcomingByService(Service $service, \DateTimeImmutable $from): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.service = :service')
            ->andWhere('a.startsAt >= :from')
            ->setParameter('service', $service->getId(), 'ulid')
            ->setParameter('from', $from)
            ->orderBy('a.startsAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Créneau d'une activité qui commence exactement à cette date-heure.
     */
    public function findOneByServiceAndStart(Service $service, \DateTimeImmutable $startsAt): ?Availability
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.service = :service')
            ->andWhere('a.startsAt = :start')
            ->setParameter('service', $service->getId(), 'ulid')
            ->setParameter('start', $startsAt)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
