<?php

declare(strict_types=1);

namespace App\PrivateActivity\Repository;

use App\Catalog\Entity\Category;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\PrivateActivityStatus;
use App\PrivateActivity\Enum\PrivateActivityVisibility;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PrivateActivity>
 */
class PrivateActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PrivateActivity::class);
    }

    /**
     * @return PrivateActivity[]
     */
    public function findByOrganizer(User $organizer): array
    {
        return $this->findBy(['organizer' => $organizer], ['createdAt' => 'DESC']);
    }

    /**
     * Découverte publique (§5, §12.3 du CDC) : visible de tous, y compris un
     * visiteur non connecté. Les activités MEMBERS_ONLY et PRIVATE n'y
     * figurent jamais, quel que soit le visiteur — c'est le rôle
     * d'`findVisibleTo()` de les inclure pour un membre connecté.
     *
     * @return list<PrivateActivity>
     */
    public function findPublic(?Category $category = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.visibility = :visibility')
            ->andWhere('a.status != :cancelled')
            ->setParameter('visibility', PrivateActivityVisibility::Public)
            ->setParameter('cancelled', PrivateActivityStatus::Cancelled)
            ->orderBy('a.scheduledAt', 'ASC');

        if (null !== $category) {
            $qb->andWhere('a.category = :category')->setParameter('category', $category->getId(), 'ulid');
        }

        /** @var list<PrivateActivity> $results */
        $results = $qb->getQuery()->getResult();

        return $results;
    }

    /**
     * Découverte pour un membre connecté : publiques + réservées aux membres.
     * PRIVATE reste hors de cette liste — elle ne se découvre pas, elle se
     * rejoint uniquement sur invitation ou lien direct (§12.3 du CDC).
     *
     * @return list<PrivateActivity>
     */
    public function findVisibleToMembers(?Category $category = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.visibility IN (:visibilities)')
            ->andWhere('a.status != :cancelled')
            ->setParameter('visibilities', [PrivateActivityVisibility::Public, PrivateActivityVisibility::MembersOnly])
            ->setParameter('cancelled', PrivateActivityStatus::Cancelled)
            ->orderBy('a.scheduledAt', 'ASC');

        if (null !== $category) {
            $qb->andWhere('a.category = :category')->setParameter('category', $category->getId(), 'ulid');
        }

        /** @var list<PrivateActivity> $results */
        $results = $qb->getQuery()->getResult();

        return $results;
    }
}
