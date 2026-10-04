<?php

declare(strict_types=1);

namespace App\Event\Entity;

use App\Event\Repository\EventInvitationRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Invitation à un événement (étape 7 de l'assistant, 04/10) : un membre
 * (notifié dans l'application) ou une adresse e-mail extérieure. Un
 * événement privé n'est visible que de son organisateur et de ses invités.
 */
#[ORM\Entity(repositoryClass: EventInvitationRepository::class)]
#[ORM\HasLifecycleCallbacks]
class EventInvitation
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: Event::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Event $event;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $user;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email;

    public function __construct(Event $event, ?User $user, ?string $email = null)
    {
        $this->event = $event;
        $this->user = $user;
        $this->email = $email;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getEmail(): ?string
    {
        return $this->email ?? $this->user?->getEmail();
    }
}
