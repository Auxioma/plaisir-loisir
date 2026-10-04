<?php

declare(strict_types=1);

namespace App\Event\Repository;

use App\Event\Entity\Event;
use App\Event\Entity\EventInvitation;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventInvitation>
 */
class EventInvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventInvitation::class);
    }

    public function isInvited(Event $event, User $user): bool
    {
        return $this->count(['event' => $event, 'user' => $user]) > 0
            || $this->count(['event' => $event, 'email' => $user->getEmail()]) > 0;
    }
}
