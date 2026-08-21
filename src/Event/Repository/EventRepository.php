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

    public function findOneBySlug(string $slug): ?Event
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}
