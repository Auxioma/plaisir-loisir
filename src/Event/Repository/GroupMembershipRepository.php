<?php

declare(strict_types=1);

namespace App\Event\Repository;

use App\Event\Entity\Group;
use App\Event\Entity\GroupMembership;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GroupMembership>
 */
class GroupMembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GroupMembership::class);
    }

    public function findOneFor(Group $group, User $user): ?GroupMembership
    {
        return $this->findOneBy(['group' => $group, 'user' => $user]);
    }

    /**
     * Membres acceptés, les plus anciens d'abord.
     *
     * @return list<GroupMembership>
     */
    public function findMembers(Group $group): array
    {
        return $this->findByStatus($group, GroupMembership::MEMBER, 'joinedAt');
    }

    /**
     * Demandes en attente, les plus anciennes d'abord.
     *
     * @return list<GroupMembership>
     */
    public function findPending(Group $group): array
    {
        return $this->findByStatus($group, GroupMembership::PENDING, 'createdAt');
    }

    public function countMembers(Group $group): int
    {
        return $this->count(['group' => $group, 'status' => GroupMembership::MEMBER]);
    }

    /**
     * @return list<GroupMembership>
     */
    private function findByStatus(Group $group, string $status, string $orderBy): array
    {
        /** @var list<GroupMembership> $results */
        $results = $this->createQueryBuilder('m')
            ->innerJoin('m.user', 'u')->addSelect('u')
            ->andWhere('m.group = :group')
            ->andWhere('m.status = :status')
            ->orderBy('m.'.$orderBy, 'ASC')
            ->setParameter('group', $group->getId(), 'ulid')
            ->setParameter('status', $status)
            ->getQuery()
            ->getResult();

        return $results;
    }
}
