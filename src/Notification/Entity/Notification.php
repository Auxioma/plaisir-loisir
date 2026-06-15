<?php

declare(strict_types=1);

namespace App\Notification\Entity;

use App\Notification\Enum\NotificationCategory;
use App\Notification\Repository\NotificationRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Notification « in-app » adressée à un utilisateur. L'envoi par e-mail/push et
 * les préférences de fréquence sont une couche de livraison séparée (plus tard).
 */
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Index(columns: ['recipient_id'])]
#[ORM\HasLifecycleCallbacks]
class Notification
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $recipient = null;

    #[ORM\Column(enumType: NotificationCategory::class)]
    private NotificationCategory $category;

    #[ORM\Column(length: 150)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $message;

    /**
     * Date de lecture ; null tant que la notification n'a pas été lue.
     */
    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    public function getRecipient(): ?User
    {
        return $this->recipient;
    }

    public function setRecipient(?User $recipient): static
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function getCategory(): NotificationCategory
    {
        return $this->category;
    }

    public function setCategory(NotificationCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

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

    /**
     * Marque la notification comme lue (idempotent : ne réécrit pas la date).
     */
    public function markAsRead(): void
    {
        $this->readAt ??= new \DateTimeImmutable();
    }
}
