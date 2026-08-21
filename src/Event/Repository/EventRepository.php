<?php

declare(strict_types=1);

namespace App\Event\Repository;

use App\Event\Entity\Event;
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
            ->orderBy('e.position', 'ASC')
            ->addOrderBy('e.startsAt', 'ASC');

        if (null !== $private) {
            $qb->andWhere('e.private = :prive')->setParameter('prive', $private);
        }

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
}
