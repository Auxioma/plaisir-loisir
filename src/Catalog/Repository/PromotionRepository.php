<?php

declare(strict_types=1);

namespace App\Catalog\Repository;

use App\Catalog\Entity\Promotion;
use App\Catalog\Entity\Service;
use App\Provider\Entity\ProviderProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Promotion>
 */
class PromotionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Promotion::class);
    }

    /**
     * @return list<Promotion>
     */
    public function findForProvider(ProviderProfile $provider): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.service', 's')->addSelect('s')
            ->andWhere('p.provider = :provider')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('provider', $provider->getId(), 'ulid')
            ->orderBy('p.startsAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** L'offre en cours sur une activité (la plus forte réduction si plusieurs). */
    public function findRunningForService(Service $service): ?Promotion
    {
        $now = new \DateTimeImmutable();

        /** @var list<Promotion> $rows */
        $rows = $this->createQueryBuilder('p')
            ->addSelect('COALESCE(p.discountPercent, 0) AS HIDDEN discount')
            ->andWhere('p.provider = :provider')
            ->andWhere('p.service = :service OR p.service IS NULL')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.paused = false')
            ->andWhere('p.startsAt <= :now AND p.endsAt >= :now')
            ->setParameter('provider', $service->getProvider()?->getId(), 'ulid')
            ->setParameter('service', $service->getId(), 'ulid')
            ->setParameter('now', $now)
            ->orderBy('discount', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        return $rows[0] ?? null;
    }

    /**
     * Offres en cours visibles du public (page « Offres du moment », 04/10) :
     * non suspendues, dans leur période, d'un professionnel dont l'activité
     * (ou au moins une activité, pour une offre « toutes activités ») est publiée.
     *
     * @return list<Promotion>
     */
    public function findRunningPublic(?\DateTimeImmutable $now = null): array
    {
        /** @var list<Promotion> $rows */
        $rows = $this->createQueryBuilder('p')
            ->leftJoin('p.service', 's')->addSelect('s')
            ->join('p.provider', 'pr')->addSelect('pr')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.paused = false')
            ->andWhere('p.startsAt <= :now AND p.endsAt >= :now')
            ->andWhere('s.id IS NULL OR (s.status = :published AND s.deletedAt IS NULL)')
            ->setParameter('now', $now ?? new \DateTimeImmutable())
            ->setParameter('published', \App\Catalog\Enum\ServiceStatus::Published)
            ->orderBy('p.endsAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
