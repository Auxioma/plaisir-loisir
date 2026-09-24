<?php

declare(strict_types=1);

namespace App\Admin\Entity;

use App\Admin\Enum\AuditAction;
use App\Admin\Repository\AuditLogRepository;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Historique des opérations sensibles d'administration (§18.1 du CDC).
 *
 * IMMUABLE — PAS DE TimestampableTrait
 * Une ligne se crée, ne se modifie jamais : `TimestampableTrait` porte un
 * `updatedAt` qui n'a ici aucun sens (rien ne « met à jour » un journal
 * d'audit sans le vider de son sens). Seule `createdAt` existe.
 *
 * `targetLabel` fige un intitulé lisible, même principe que
 * `Report::subjectLabel` — voir son commentaire.
 */
#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
class AuditLog
{
    use UlidIdentifierTrait;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $actor = null;

    #[ORM\Column(enumType: AuditAction::class)]
    private AuditAction $action;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $targetType = null;

    #[ORM\Column(type: 'ulid', nullable: true)]
    private ?Ulid $targetId = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $targetLabel = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $details = null;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function setActor(?User $actor): static
    {
        $this->actor = $actor;

        return $this;
    }

    public function getAction(): AuditAction
    {
        return $this->action;
    }

    public function setAction(AuditAction $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getTargetType(): ?string
    {
        return $this->targetType;
    }

    public function setTargetType(?string $targetType): static
    {
        $this->targetType = $targetType;

        return $this;
    }

    public function getTargetId(): ?Ulid
    {
        return $this->targetId;
    }

    public function setTargetId(?Ulid $targetId): static
    {
        $this->targetId = $targetId;

        return $this;
    }

    public function getTargetLabel(): ?string
    {
        return $this->targetLabel;
    }

    public function setTargetLabel(?string $targetLabel): static
    {
        $this->targetLabel = $targetLabel;

        return $this;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function setDetails(?string $details): static
    {
        $this->details = $details;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
