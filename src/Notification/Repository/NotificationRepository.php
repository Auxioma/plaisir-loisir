<?php

declare(strict_types=1);

namespace App\Notification\Repository;

use App\Notification\Entity\Notification;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * @return Notification[]
     */
    public function findUnreadByRecipient(User $recipient): array
    {
        return $this->findBy(['recipient' => $recipient, 'readAt' => null], ['createdAt' => 'DESC']);
    }

    public function countUnread(User $recipient): int
    {
        return $this->count(['recipient' => $recipient, 'readAt' => null]);
    }
}
