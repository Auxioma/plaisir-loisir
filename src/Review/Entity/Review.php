<?php

declare(strict_types=1);

namespace App\Review\Entity;

use App\Booking\Entity\Booking;
use App\Catalog\Entity\Service;
use App\Review\Enum\ReviewStatus;
use App\Review\Repository\ReviewRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Avis laissé par un client sur une activité, adossé à une réservation terminée
 * (preuve d'achat, pour limiter les faux avis). Un avis par réservation.
 */
#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\Index(columns: ['service_id'])]
#[ORM\HasLifecycleCallbacks]
class Review
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $author = null;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Service $service = null;

    #[ORM\ManyToOne(targetEntity: Booking::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?Booking $booking = null;

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

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;

        return $this;
    }

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }

    public function setBooking(?Booking $booking): static
    {
        $this->booking = $booking;

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
