<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use App\Catalog\Enum\PromotionKind;
use App\Catalog\Repository\PromotionRepository;
use App\Provider\Entity\ProviderProfile;
use App\Shared\Doctrine\SoftDeletableTrait;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Offre promotionnelle d'un professionnel sur une de ses activités (ou sur
 * toutes si `service` est nul) — espace pro « Offres & Promotions » (02/10).
 *
 * Le statut n'est pas stocké : il découle des dates (planifiée, active,
 * terminée), sauf mise en pause explicite par le professionnel ou
 * l'administration.
 */
#[ORM\Entity(repositoryClass: PromotionRepository::class)]
#[ORM\Index(columns: ['provider_id'])]
#[ORM\HasLifecycleCallbacks]
class Promotion
{
    use UlidIdentifierTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ENDED = 'ended';
    public const STATUS_PAUSED = 'paused';

    #[ORM\ManyToOne(targetEntity: ProviderProfile::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ProviderProfile $provider = null;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Service $service = null;

    #[ORM\Column(length: 120)]
    private string $title = '';

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $subtitle = null;

    #[ORM\Column(enumType: PromotionKind::class)]
    private PromotionKind $kind = PromotionKind::Reduction;

    /** Pourcentage de réduction (1 à 90), pour les offres de type réduction. */
    #[ORM\Column(nullable: true)]
    private ?int $discountPercent = null;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $endsAt;

    #[ORM\Column(options: ['default' => false])]
    private bool $paused = false;

    /** Affichages de l'offre (fiche de l'activité pendant la période). */
    #[ORM\Column(options: ['default' => 0])]
    private int $viewsCount = 0;

    /** Clics sur l'offre (arrivées sur l'activité via le lien de l'offre). */
    #[ORM\Column(options: ['default' => 0])]
    private int $clicksCount = 0;

    public function __construct()
    {
        $this->startsAt = new \DateTimeImmutable('today');
        $this->endsAt = new \DateTimeImmutable('+30 days');
    }

    public function __toString(): string
    {
        return $this->title;
    }

    public function getStatus(?\DateTimeImmutable $now = null): string
    {
        $now ??= new \DateTimeImmutable();

        return match (true) {
            $this->paused => self::STATUS_PAUSED,
            $now < $this->startsAt => self::STATUS_SCHEDULED,
            $now > $this->endsAt => self::STATUS_ENDED,
            default => self::STATUS_ACTIVE,
        };
    }

    public function isRunning(?\DateTimeImmutable $now = null): bool
    {
        return self::STATUS_ACTIVE === $this->getStatus($now);
    }

    /** Pastille de la vignette (« -20% », « 2=1 »…). */
    public function getBadge(): string
    {
        return match ($this->kind) {
            PromotionKind::Reduction => null !== $this->discountPercent ? '-'.$this->discountPercent.'%' : 'Promo',
            PromotionKind::TwoForOne => '2=1',
            PromotionKind::Special => 'Spécial',
            PromotionKind::Other => 'Offre',
        };
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

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;

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

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): static
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    public function getKind(): PromotionKind
    {
        return $this->kind;
    }

    public function setKind(PromotionKind $kind): static
    {
        $this->kind = $kind;

        return $this;
    }

    public function getDiscountPercent(): ?int
    {
        return $this->discountPercent;
    }

    public function setDiscountPercent(?int $discountPercent): static
    {
        $this->discountPercent = null !== $discountPercent ? max(1, min(90, $discountPercent)) : null;

        return $this;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(\DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getEndsAt(): \DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(\DateTimeImmutable $endsAt): static
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function isPaused(): bool
    {
        return $this->paused;
    }

    public function setPaused(bool $paused): static
    {
        $this->paused = $paused;

        return $this;
    }

    public function getViewsCount(): int
    {
        return $this->viewsCount;
    }

    public function setViewsCount(int $viewsCount): static
    {
        $this->viewsCount = $viewsCount;

        return $this;
    }

    public function getClicksCount(): int
    {
        return $this->clicksCount;
    }

    public function setClicksCount(int $clicksCount): static
    {
        $this->clicksCount = $clicksCount;

        return $this;
    }
}
