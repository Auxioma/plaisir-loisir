<?php

declare(strict_types=1);

namespace App\Messaging\Repository;

use App\Messaging\Entity\Message;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * Nombre de messages non lus adressés à l'utilisateur, tous fils confondus
     * (badge sidebar, §15 du CDC).
     */
    public function countUnreadForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->join('m.conversation', 'c')
            ->leftJoin('c.provider', 'p')
            ->andWhere('c.client = :user OR p.user = :user')
            ->andWhere('m.author != :user')
            ->andWhere('m.readAt IS NULL')
            ->setParameter('user', $user->getId(), 'ulid')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
