<?php

declare(strict_types=1);

namespace App\Event\Repository;

use App\Event\Entity\Group;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Group>
 */
class GroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Group::class);
    }

    /**
     * Les groupes dans l'ordre d'affichage de la maquette.
     *
     * @return list<Group>
     */
    public function findForListing(?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('g')
            ->andWhere('g.deletedAt IS NULL')
            ->orderBy('g.position', 'ASC');

        if (null !== $limit) {
            // Aucune collection jointe : setMaxResults limite bien des
            // entites. Le jour ou les albums seront joints ici, il faudra
            // passer par Paginator.
            $qb->setMaxResults($limit);
        }

        /** @var list<Group> $results */
        $results = $qb->getQuery()->getResult();

        return $results;
    }

    public function findOneBySlug(string $slug): ?Group
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}
