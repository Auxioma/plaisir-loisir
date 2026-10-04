<?php

declare(strict_types=1);

namespace App\Event\Repository;

use App\Event\Entity\Event;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * Les evenements a afficher, dans l'ordre de la maquette.
     *
     * La categorie est jointe : sans cela, chaque carte irait chercher son
     * badge par une requete supplementaire.
     *
     * @return list<Event>
     */
    public function findForListing(?bool $private = null, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->addSelect('c')
            ->leftJoin('e.category', 'c')
            ->andWhere('e.deletedAt IS NULL')
            ->andWhere("(e.status = 'published' OR (e.status = 'scheduled' AND e.publishAt <= :now))")
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('e.position', 'ASC')
            ->addOrderBy('e.startsAt', 'ASC');

        // Par défaut, seuls les événements publics (04/10) : un événement
        // privé n'est montré qu'à son organisateur et à ses invités.
        $qb->andWhere('e.private = :prive')->setParameter('prive', $private ?? false);

        if (null !== $limit) {
            // Aucune collection n'est jointe ici : setMaxResults limite bien
            // des entites et non des lignes. Le jour ou une collection le
            // sera, il faudra passer par Paginator.
            $qb->setMaxResults($limit);
        }

        /** @var list<Event> $results */
        $results = $qb->getQuery()->getResult();

        return $results;
    }

    /**
     * Les evenements compris dans un intervalle, pour une grille de calendrier.
     *
     * @return list<Event>
     */
    public function findBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<Event> $results */
        $results = $this->createQueryBuilder('e')
            ->addSelect('c')
            ->leftJoin('e.category', 'c')
            ->andWhere('e.deletedAt IS NULL')
            ->andWhere('e.private = false')
            ->andWhere('e.showInCalendar = true')
            ->andWhere("(e.status = 'published' OR (e.status = 'scheduled' AND e.publishAt <= :now))")
            ->setParameter('now', new \DateTimeImmutable())
            ->andWhere('e.startsAt >= :debut')
            ->andWhere('e.startsAt < :fin')
            ->setParameter('debut', $from)
            ->setParameter('fin', $to)
            ->orderBy('e.startsAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $results;
    }

    /**
     * Le mois a ouvrir par defaut sur le calendrier.
     *
     * Celui du prochain evenement a venir ; a defaut, celui du dernier passe ;
     * a defaut encore, le mois courant. Ouvrir sur un mois vide alors que le
     * site a des evenements donnerait l'impression qu'il n'y en a aucun.
     */
    public function findDefaultCalendarMonth(): \DateTimeImmutable
    {
        $maintenant = new \DateTimeImmutable();

        $prochain = $this->createQueryBuilder('e')
            ->andWhere('e.deletedAt IS NULL')
            ->andWhere('e.startsAt >= :maintenant')
            ->setParameter('maintenant', $maintenant)
            ->orderBy('e.startsAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$prochain instanceof Event) {
            $prochain = $this->createQueryBuilder('e')
                ->andWhere('e.deletedAt IS NULL')
                ->orderBy('e.startsAt', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        }

        return $prochain instanceof Event ? $prochain->getStartsAt() : $maintenant;
    }

    public function findOneBySlug(string $slug): ?Event
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * « Mes activités créées » de l'espace compte (Lot J, 15/09), même
     * patron que PrivateActivityRepository::findByOrganizer.
     *
     * @return list<Event>
     */
    public function findByOrganizer(User $organizer): array
    {
        /** @var list<Event> $results */
        $results = $this->createQueryBuilder('e')
            ->andWhere('e.organizer = :organizer')
            ->andWhere('e.deletedAt IS NULL')
            ->orderBy('e.startsAt', 'DESC')
            ->setParameter('organizer', $organizer->getId(), 'ulid')
            ->getQuery()
            ->getResult();

        return $results;
    }

    /**
     * Recherche de la page « Événements » (maquette evenements.jpeg, 04/10) :
     * événements publics publiés, à venir, filtrés et triés.
     *
     * @param array{q?: string, where?: string, when?: string, date?: string, category?: string, type?: string, sort?: string} $f
     *
     * @return array{0: list<Event>, 1: int}
     */
    public function searchPublic(array $f, int $limit, int $offset = 0): array
    {
        $now = new \DateTimeImmutable();
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.category', 'c')->addSelect('c')
            ->andWhere('e.deletedAt IS NULL')
            ->andWhere('e.private = false')
            ->andWhere("(e.status = 'published' OR (e.status = 'scheduled' AND e.publishAt <= :now))")
            ->andWhere('COALESCE(e.endsAt, e.startsAt) >= :today')
            ->setParameter('now', $now)
            ->setParameter('today', $now->setTime(0, 0));

        if ('' !== ($q = trim((string) ($f['q'] ?? '')))) {
            $qb->andWhere('LOWER(e.title) LIKE :q OR LOWER(e.location) LIKE :q OR LOWER(COALESCE(e.shortDescription, \'\')) LIKE :q OR LOWER(COALESCE(c.name, \'\')) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower($q).'%');
        }
        if ('' !== ($where = trim((string) ($f['where'] ?? '')))) {
            $qb->andWhere('LOWER(COALESCE(e.city, \'\')) LIKE :w OR LOWER(COALESCE(e.location, \'\')) LIKE :w OR COALESCE(e.postalCode, \'\') LIKE :w')
                ->setParameter('w', '%'.mb_strtolower($where).'%');
        }
        [$from, $to] = $this->period((string) ($f['when'] ?? ''), (string) ($f['date'] ?? ''), $now);
        if (null !== $from) {
            $qb->andWhere('e.startsAt >= :from AND e.startsAt < :to')->setParameter('from', $from)->setParameter('to', $to);
        }
        if ('' !== ($category = (string) ($f['category'] ?? ''))) {
            $qb->andWhere('c.slug = :cat')->setParameter('cat', $category);
        }
        if ('' !== ($type = (string) ($f['type'] ?? ''))) {
            $qb->andWhere('e.eventType = :type')->setParameter('type', $type);
        }

        $total = (int) (clone $qb)->select('COUNT(e.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        match ($f['sort'] ?? 'date') {
            'populaires' => $qb->orderBy('e.participantsCount', 'DESC')->addOrderBy('e.startsAt', 'ASC'),
            'recents' => $qb->orderBy('e.createdAt', 'DESC'),
            default => $qb->orderBy('e.startsAt', 'ASC'),
        };

        /** @var list<Event> $rows */
        $rows = $qb->setFirstResult($offset)->setMaxResults($limit)->getQuery()->getResult();

        return [$rows, $total];
    }

    /** @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable} */
    private function period(string $when, string $date, \DateTimeImmutable $now): array
    {
        $today = $now->setTime(0, 0);
        if (1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && false !== ($day = \DateTimeImmutable::createFromFormat('!Y-m-d', $date))) {
            return [$day, $day->modify('+1 day')];
        }

        return match ($when) {
            'aujourdhui' => [$today, $today->modify('+1 day')],
            'demain' => [$today->modify('+1 day'), $today->modify('+2 days')],
            'weekend' => [$today->modify('saturday this week'), $today->modify('monday next week')],
            'semaine' => [$today, $today->modify('+7 days')],
            'mois' => [$today, $today->modify('+1 month')],
            default => [null, null],
        };
    }

    /**
     * Événements privés visibles d'un membre : ceux qu'il organise ou
     * auxquels il est invité.
     *
     * @return list<Event>
     */
    public function findPrivateFor(User $user): array
    {
        /** @var list<Event> $rows */
        $rows = $this->createQueryBuilder('e')
            ->leftJoin('e.category', 'c')->addSelect('c')
            ->leftJoin(\App\Event\Entity\EventInvitation::class, 'i', 'WITH', 'i.event = e AND (i.user = :user OR i.email = :email)')
            ->andWhere('e.deletedAt IS NULL')
            ->andWhere('e.private = true')
            ->andWhere("e.status != 'draft'")
            ->andWhere('e.organizer = :user OR i.id IS NOT NULL')
            ->setParameter('user', $user->getId(), 'ulid')
            ->setParameter('email', $user->getEmail())
            ->orderBy('e.startsAt', 'ASC')
            ->getQuery()->getResult();

        return array_values(array_unique($rows, \SORT_REGULAR));
    }
}
