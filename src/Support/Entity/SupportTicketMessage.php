<?php

declare(strict_types=1);

namespace App\Support\Entity;

use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un message d'un ticket : du professionnel, ou de l'équipe (`fromStaff`).
 */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class SupportTicketMessage
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: SupportTicket::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?SupportTicket $ticket = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $fromStaff = false;

    #[ORM\Column(type: 'text')]
    private string $body = '';

    public function getTicket(): ?SupportTicket
    {
        return $this->ticket;
    }

    public function setTicket(?SupportTicket $ticket): static
    {
        $this->ticket = $ticket;

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function isFromStaff(): bool
    {
        return $this->fromStaff;
    }

    public function setFromStaff(bool $fromStaff): static
    {
        $this->fromStaff = $fromStaff;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;

        return $this;
    }
}
