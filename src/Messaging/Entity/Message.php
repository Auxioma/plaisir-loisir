<?php

declare(strict_types=1);

namespace App\Messaging\Entity;

use App\Messaging\Repository\MessageRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Message posté dans une conversation par l'un des participants.
 */
#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Message
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: Conversation::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Conversation $conversation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $author = null;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    public function getConversation(): ?Conversation
    {
        return $this->conversation;
    }

    public function setConversation(?Conversation $conversation): static
    {
        $this->conversation = $conversation;

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

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function isRead(): bool
    {
        return null !== $this->readAt;
    }

    public function markAsRead(): void
    {
        $this->readAt ??= new \DateTimeImmutable();
    }
}
