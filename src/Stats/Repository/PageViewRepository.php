<?php

declare(strict_types=1);

namespace App\Stats\Repository;

use App\Catalog\Entity\Service;
use App\Provider\Entity\ProviderProfile;
use App\Stats\Entity\PageView;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PageView>
 */
class PageViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageView::class);
    }

    /**
     * Consultations d'un professionnel sur une période.
     *
     * @return list<PageView>
     */
    public function findForProvider(ProviderProfile $provider, \DateTimeImmutable $from, \DateTimeImmutable $to, ?string $kind = null): array
    {
        $qb = $this->createQueryBuilder('v')
            ->andWhere('v.provider = :provider')
            ->andWhere('v.viewedAt BETWEEN :from AND :to')
            ->setParameter('provider', $provider->getId(), 'ulid')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('v.viewedAt', 'ASC');

        if (null !== $kind) {
            $qb->andWhere('v.kind = :kind')->setParameter('kind', $kind);
        }

        return $qb->getQuery()->getResult();
    }

    public function countForService(Service $service, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): int
    {
        $qb = $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->andWhere('v.service = :service')
            ->andWhere('v.kind = :kind')
            ->setParameter('service', $service->getId(), 'ulid')
            ->setParameter('kind', PageView::KIND_ACTIVITY);

        if (null !== $from && null !== $to) {
            $qb->andWhere('v.viewedAt BETWEEN :from AND :to')->setParameter('from', $from)->setParameter('to', $to);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countForProvider(ProviderProfile $provider, string $kind): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->andWhere('v.provider = :provider')
            ->andWhere('v.kind = :kind')
            ->setParameter('provider', $provider->getId(), 'ulid')
            ->setParameter('kind', $kind)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
