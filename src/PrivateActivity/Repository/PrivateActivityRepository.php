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
            ->andWhere('a.status NOT IN (:hidden)')
            ->setParameter('visibility', PrivateActivityVisibility::Public)
            ->setParameter('hidden', [PrivateActivityStatus::Cancelled, PrivateActivityStatus::Draft])
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
            ->andWhere('a.status NOT IN (:hidden)')
            ->setParameter('visibilities', [PrivateActivityVisibility::Public, PrivateActivityVisibility::MembersOnly])
            ->setParameter('hidden', [PrivateActivityStatus::Cancelled, PrivateActivityStatus::Draft])
            ->orderBy('a.scheduledAt', 'ASC');

        if (null !== $category) {
            $qb->andWhere('a.category = :category')->setParameter('category', $category->getId(), 'ulid');
        }

        /** @var list<PrivateActivity> $results */
        $results = $qb->getQuery()->getResult();

        return $results;
    }

    /**
     * Activités à venir que l'on peut découvrir (05/10) : publiques, plus
     * celles réservées aux membres si `$members`. Filtres de la recherche de
     * l'accueil et de /activites-privees : lieu (ville ou code postal),
     * mot-clé (titre), jour, catégorie.
     *
     * @return list<PrivateActivity>
     */
    public function findUpcomingDiscoverable(
        bool $members,
        ?Category $category = null,
        ?string $place = null,
        ?string $keywords = null,
        ?\DateTimeImmutable $day = null,
        ?int $limit = null,
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.category', 'c')->addSelect('c')
            ->andWhere('a.visibility IN (:visibilities)')
            ->andWhere('a.status NOT IN (:hidden)')
            ->andWhere('a.scheduledAt IS NULL OR a.scheduledAt >= :now')
            ->setParameter('visibilities', $members ? [PrivateActivityVisibility::Public, PrivateActivityVisibility::MembersOnly] : [PrivateActivityVisibility::Public])
            ->setParameter('hidden', [PrivateActivityStatus::Cancelled, PrivateActivityStatus::Draft])
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('a.scheduledAt', 'ASC');

        if (null !== $category) {
            $qb->andWhere('a.category = :category')->setParameter('category', $category->getId(), 'ulid');
        }
        if (null !== $place && '' !== trim($place)) {
            $qb->andWhere('LOWER(a.city) LIKE :place OR a.postalCode LIKE :placeStart')
                ->setParameter('place', '%'.mb_strtolower(trim($place)).'%')
                ->setParameter('placeStart', trim($place).'%');
        }
        if (null !== $keywords && '' !== trim($keywords)) {
            $qb->andWhere('LOWER(a.title) LIKE :kw OR LOWER(a.description) LIKE :kw')
                ->setParameter('kw', '%'.mb_strtolower(trim($keywords)).'%');
        }
        if (null !== $day) {
            $qb->andWhere('a.scheduledAt >= :dayStart AND a.scheduledAt < :dayEnd')
                ->setParameter('dayStart', $day->setTime(0, 0))
                ->setParameter('dayEnd', $day->setTime(0, 0)->modify('+1 day'));
        }
        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        /** @var list<PrivateActivity> $results */
        $results = $qb->getQuery()->getResult();

        return $results;
    }
}
