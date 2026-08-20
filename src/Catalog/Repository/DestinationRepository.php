<?php

declare(strict_types=1);

namespace App\Catalog\Repository;

use App\Catalog\Entity\Destination;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Destination>
 */
class DestinationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Destination::class);
    }

    /**
     * Les destinations dans l'ordre d'affichage de la maquette.
     *
     * @return list<Destination>
     */
    public function findForListing(?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->orderBy('d.position', 'ASC')
            ->addOrderBy('d.createdAt', 'ASC');

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        /** @var list<Destination> $results */
        $results = $qb->getQuery()->getResult();

        return $results;
    }

    public function findOneBySlug(string $slug): ?Destination
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * @param string[] $ids
     *
     * @return Destination[]
     */
    public function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->findBy(['id' => $ids]);
    }

    /**
     * Recherche des destinations par nom (toutes si la requête est vide).
     *
     * @return Destination[]
     */
    public function searchByName(string $query, int $limit = 20): array
    {
        $query = trim($query);
        if ('' === $query) {
            return $this->findBy([], ['name' => 'ASC'], $limit);
        }

        return $this->createQueryBuilder('d')
            ->andWhere('LOWER(d.name) LIKE :q')
            ->setParameter('q', '%'.mb_strtolower($query).'%')
            ->orderBy('d.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
