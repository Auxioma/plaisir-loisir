<?php

declare(strict_types=1);

namespace App\Event\Repository;

use App\Event\Entity\Group;
use App\Event\Entity\GroupAlbum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GroupAlbum>
 */
class GroupAlbumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GroupAlbum::class);
    }

    /**
     * @return list<GroupAlbum>
     */
    public function findForGroup(Group $group): array
    {
        /** @var list<GroupAlbum> $results */
        $results = $this->createQueryBuilder('a')
            ->andWhere('a.group = :groupe')
            ->setParameter('groupe', $group->getId(), 'ulid')
            ->orderBy('a.position', 'ASC')
            ->getQuery()
            ->getResult();

        return $results;
    }
}
