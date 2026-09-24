<?php

declare(strict_types=1);

namespace App\Review\Entity;

use App\Provider\Entity\ProviderProfile;
use App\Quote\Entity\Quote;
use App\Review\Enum\ReviewStatus;
use App\Review\Repository\ReviewRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Avis laissé par un client sur un professionnel, adossé à un devis accepté
 * (preuve d'une relation réelle, pour limiter les faux avis, §16.2 du CDC).
 * Un avis par devis.
 *
 * Anciennement adossé à Booking (catalogue à réservation directe, mis en
 * pause le 11/09) : reconnecté au modèle demande/devis le 15/09 (Lot H).
 */
#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\Index(columns: ['provider_id'])]
#[ORM\HasLifecycleCallbacks]
class Review
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $author = null;

    #[ORM\ManyToOne(targetEntity: ProviderProfile::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ProviderProfile $provider = null;

    #[ORM\ManyToOne(targetEntity: Quote::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?Quote $quote = null;

    /**
     * Note de 1 à 5 (en étoiles).
     */
    #[ORM\Column]
    private int $rating;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(enumType: ReviewStatus::class, options: ['default' => 'published'])]
    private ReviewStatus $status = ReviewStatus::Published;

    /**
     * Réponse publique de l'annonceur à l'avis (et sa date).
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $providerReply = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $repliedAt = null;

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getProvider(): ?ProviderProfile
    {
        return $this->provider;
    }

    public function setProvider(?ProviderProfile $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getQuote(): ?Quote
    {
        return $this->quote;
    }

    public function setQuote(?Quote $quote): static
    {
        $this->quote = $quote;

        return $this;
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function setRating(int $rating): static
    {
        $this->rating = $rating;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getStatus(): ReviewStatus
    {
        return $this->status;
    }

    public function approve(): void
    {
        $this->status = ReviewStatus::Published;
    }

    public function reject(): void
    {
        $this->status = ReviewStatus::Rejected;
    }

    public function getProviderReply(): ?string
    {
        return $this->providerReply;
    }

    public function getRepliedAt(): ?\DateTimeImmutable
    {
        return $this->repliedAt;
    }

    /**
     * Enregistre la réponse de l'annonceur (et l'horodate).
     */
    public function reply(string $text): void
    {
        $this->providerReply = $text;
        $this->repliedAt = new \DateTimeImmutable();
    }
}
