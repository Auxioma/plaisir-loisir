<?php

declare(strict_types=1);

namespace App\Event\Repository;

use App\Event\Entity\Event;
use App\Event\Entity\EventRegistration;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventRegistration>
 */
class EventRegistrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventRegistration::class);
    }

    public function findOneFor(Event $event, User $user): ?EventRegistration
    {
        return $this->findOneBy(['event' => $event, 'user' => $user]);
    }

    /** @return list<EventRegistration> */
    public function findGoing(Event $event, ?int $limit = null): array
    {
        return $this->findBy(['event' => $event, 'status' => EventRegistration::GOING], ['createdAt' => 'ASC'], $limit);
    }

    public function countGoing(Event $event): int
    {
        return $this->count(['event' => $event, 'status' => EventRegistration::GOING]);
    }
}
