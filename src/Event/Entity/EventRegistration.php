<?php

declare(strict_types=1);

namespace App\Event\Entity;

use App\Event\Repository\EventRegistrationRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Inscription d'un membre à un événement (04/10) : « going » (inscrit) ou
 * « waitlist » (liste d'attente quand l'événement est complet).
 */
#[ORM\Entity(repositoryClass: EventRegistrationRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_event_registration', columns: ['event_id', 'user_id'])]
#[ORM\HasLifecycleCallbacks]
class EventRegistration
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    public const GOING = 'going';
    public const WAITLIST = 'waitlist';

    #[ORM\ManyToOne(targetEntity: Event::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Event $event;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 20)]
    private string $status;

    public function __construct(Event $event, User $user, string $status = self::GOING)
    {
        $this->event = $event;
        $this->user = $user;
        $this->status = $status;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }
}
