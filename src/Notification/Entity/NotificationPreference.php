<?php

declare(strict_types=1);

namespace App\Notification\Entity;

use App\Notification\Repository\NotificationPreferenceRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Préférences de notification d'un utilisateur (canaux). La fréquence fine
 * pourra être ajoutée plus tard.
 */
#[ORM\Entity(repositoryClass: NotificationPreferenceRepository::class)]
#[ORM\HasLifecycleCallbacks]
class NotificationPreference
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $emailEnabled = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $pushEnabled = true;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function isEmailEnabled(): bool
    {
        return $this->emailEnabled;
    }

    public function setEmailEnabled(bool $emailEnabled): static
    {
        $this->emailEnabled = $emailEnabled;

        return $this;
    }

    public function isPushEnabled(): bool
    {
        return $this->pushEnabled;
    }

    public function setPushEnabled(bool $pushEnabled): static
    {
        $this->pushEnabled = $pushEnabled;

        return $this;
    }
}
