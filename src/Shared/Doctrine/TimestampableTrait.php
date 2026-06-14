<?php

declare(strict_types=1);

namespace App\Shared\Doctrine;

use Doctrine\ORM\Mapping as ORM;

/**
 * Horodatage automatique de création et de dernière modification.
 *
 * IMPORTANT : l'entité qui utilise ce trait doit être annotée
 * avec #[ORM\HasLifecycleCallbacks] pour que les callbacks s'exécutent.
 *
 * Le type "datetimetz_immutable" est stocké en TIMESTAMPTZ (avec fuseau horaire) sous PostgreSQL.
 */
trait TimestampableTrait
{
    #[ORM\Column(type: 'datetimetz_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function initTimestamps(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
