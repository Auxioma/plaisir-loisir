<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use App\Catalog\Repository\CategorySuggestionRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Catégorie proposée par un membre ou un professionnel depuis un assistant
 * de création (05/10), quand la sienne manque — plutôt qu'un « Autre » trop
 * vague. L'équipe la valide (une Category est créée) ou la refuse dans le
 * back-office ; l'auteur est notifié dans les deux cas.
 */
#[ORM\Entity(repositoryClass: CategorySuggestionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class CategorySuggestion
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    #[ORM\Column(length: 80)]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $requestedBy = null;

    /** Parcours d'origine : « private » (activité entre membres) ou « pro ». */
    #[ORM\Column(length: 10, options: ['default' => 'private'])]
    private string $context = 'private';

    #[ORM\Column(length: 10, options: ['default' => self::STATUS_PENDING])]
    private string $status = self::STATUS_PENDING;

    /** Catégorie parente choisie par l'équipe à la validation (facultative). */
    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Category $parent = null;

    /** Catégorie créée à la validation. */
    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Category $category = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adminNote = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;

    public function __toString(): string
    {
        return $this->name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getRequestedBy(): ?User
    {
        return $this->requestedBy;
    }

    public function setRequestedBy(?User $requestedBy): static
    {
        $this->requestedBy = $requestedBy;

        return $this;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function setContext(string $context): static
    {
        $this->context = $context;

        return $this;
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

    public function getParent(): ?Category
    {
        return $this->parent;
    }

    public function setParent(?Category $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getAdminNote(): ?string
    {
        return $this->adminNote;
    }

    public function setAdminNote(?string $adminNote): static
    {
        $this->adminNote = $adminNote;

        return $this;
    }

    public function getDecidedAt(): ?\DateTimeImmutable
    {
        return $this->decidedAt;
    }

    public function setDecidedAt(?\DateTimeImmutable $decidedAt): static
    {
        $this->decidedAt = $decidedAt;

        return $this;
    }

    public function isPending(): bool
    {
        return self::STATUS_PENDING === $this->status;
    }
}
