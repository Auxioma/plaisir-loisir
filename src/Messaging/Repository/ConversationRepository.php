<?php

declare(strict_types=1);

namespace App\Messaging\Repository;

use App\Messaging\Entity\Conversation;
use App\Provider\Entity\ProviderProfile;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    public function findOneByClientAndProvider(User $client, ProviderProfile $provider): ?Conversation
    {
        return $this->findOneBy(['client' => $client, 'provider' => $provider]);
    }

    /**
     * Toutes les conversations où l'utilisateur participe, comme client ou
     * comme annonceur.
     *
     * @return list<Conversation>
     */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.provider', 'p')
            ->addSelect('p')
            ->andWhere('c.client = :user OR p.user = :user OR c.member = :user')
            ->setParameter('user', $user->getId(), 'ulid')
            ->getQuery()
            ->getResult();
    }

    /** Conversation existante entre deux membres, dans un sens ou dans l'autre. */
    public function findBetweenMembers(User $a, User $b): ?Conversation
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.provider IS NULL')
            ->andWhere('(c.client = :a AND c.member = :b) OR (c.client = :b AND c.member = :a)')
            ->setParameter('a', $a->getId(), 'ulid')
            ->setParameter('b', $b->getId(), 'ulid')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
