<?php

declare(strict_types=1);

namespace App\PrivateActivity\Entity;

use App\PrivateActivity\Repository\MemberReviewRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Avis d'un participant sur l'organisateur d'une activité gratuite
 * (13/10, maquette « detail-profil » : note, détail des notes, avis reçus).
 *
 * Un avis par participant et par activité, déposé une fois l'activité
 * passée, par un participant dont la place avait été acceptée.
 */
#[ORM\Entity(repositoryClass: MemberReviewRepository::class)]
#[ORM\Table(name: 'member_review')]
#[ORM\UniqueConstraint(name: 'uniq_member_review_author_activity', columns: ['private_activity_id', 'author_id'])]
#[ORM\Index(name: 'idx_member_review_organizer', columns: ['organizer_id'])]
#[ORM\HasLifecycleCallbacks]
class MemberReview
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    /** Critères notés de 1 à 5, dans l'ordre d'affichage. */
    public const CRITERIA = [
        'conviviality' => 'Convivialité',
        'organisation' => 'Organisation',
        'punctuality' => 'Ponctualité',
        'communication' => 'Communication',
    ];

    #[ORM\ManyToOne(targetEntity: PrivateActivity::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PrivateActivity $privateActivity = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $author = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $organizer = null;

    #[ORM\Column(type: 'smallint')]
    private int $rating = 5;

    #[ORM\Column(type: 'smallint')]
    private int $conviviality = 5;

    #[ORM\Column(type: 'smallint')]
    private int $organisation = 5;

    #[ORM\Column(type: 'smallint')]
    private int $punctuality = 5;

    #[ORM\Column(type: 'smallint')]
    private int $communication = 5;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    public function getPrivateActivity(): ?PrivateActivity
    {
        return $this->privateActivity;
    }

    public function setPrivateActivity(PrivateActivity $activity): static
    {
        $this->privateActivity = $activity;
        $this->organizer = $activity->getOrganizer();

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(User $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getOrganizer(): ?User
    {
        return $this->organizer;
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function getCriterion(string $key): int
    {
        return match ($key) {
            'conviviality' => $this->conviviality,
            'organisation' => $this->organisation,
            'punctuality' => $this->punctuality,
            'communication' => $this->communication,
            default => throw new \InvalidArgumentException(\sprintf('Critère inconnu : %s', $key)),
        };
    }

    /**
     * @param array<string, int> $criteria notes de 1 à 5 par critère (CRITERIA)
     */
    public function rate(array $criteria): static
    {
        $clamp = static fn (mixed $v): int => max(1, min(5, (int) $v));
        $this->conviviality = $clamp($criteria['conviviality'] ?? 5);
        $this->organisation = $clamp($criteria['organisation'] ?? 5);
        $this->punctuality = $clamp($criteria['punctuality'] ?? 5);
        $this->communication = $clamp($criteria['communication'] ?? 5);
        // Note globale : moyenne arrondie des quatre critères.
        $this->rating = (int) round(($this->conviviality + $this->organisation + $this->punctuality + $this->communication) / 4);

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = null !== $comment && '' !== trim($comment) ? mb_substr(trim($comment), 0, 1000) : null;

        return $this;
    }
}
