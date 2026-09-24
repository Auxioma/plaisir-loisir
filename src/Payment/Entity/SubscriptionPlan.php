<?php

declare(strict_types=1);

namespace App\Payment\Entity;

use App\Payment\Enum\BillingPeriod;
use App\Payment\Repository\SubscriptionPlanRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Offre d'abonnement professionnel, administrable (§17.1 du CDC).
 *
 * SEUL MODÈLE DE REVENU AUTORISÉ PAR LE CDC (§1.2, §3.2) : les prestations et
 * les activités privées ne sont jamais encaissées par la plateforme, la
 * monétisation passe exclusivement par ici.
 */
#[ORM\Entity(repositoryClass: SubscriptionPlanRepository::class)]
#[ORM\HasLifecycleCallbacks]
class SubscriptionPlan
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 140, unique: true)]
    private string $slug;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(enumType: BillingPeriod::class)]
    private BillingPeriod $billingPeriod = BillingPeriod::Monthly;

    /** Montant en decimal, jamais en float (voir CLAUDE.md). */
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $priceAmount;

    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    private string $currency = 'EUR';

    /** Nombre de demandes consultables par mois. Null = illimité. */
    #[ORM\Column(nullable: true)]
    private ?int $maxRequestsPerMonth = null;

    /** Nombre de réponses (devis) envoyables par mois. Null = illimité. */
    #[ORM\Column(nullable: true)]
    private ?int $maxResponsesPerMonth = null;

    /** Nombre de catégories/métiers déclarables. Null = illimité. */
    #[ORM\Column(nullable: true)]
    private ?int $maxCategories = null;

    /** Mise en avant dans les résultats de recherche. */
    #[ORM\Column(options: ['default' => false])]
    private bool $featured = false;

    /** Statistiques avancées du tableau de bord professionnel. */
    #[ORM\Column(options: ['default' => false])]
    private bool $advancedStatistics = false;

    /**
     * Identifiant du Price Stripe correspondant (créé côté dashboard Stripe).
     * Nullable : une offre peut être préparée avant d'être reliée à Stripe.
     */
    #[ORM\Column(length: 80, nullable: true)]
    private ?string $stripePriceId = null;

    /** Une offre retirée de la vente reste visible aux abonnés déjà engagés. */
    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

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

    public function getBillingPeriod(): BillingPeriod
    {
        return $this->billingPeriod;
    }

    public function setBillingPeriod(BillingPeriod $billingPeriod): static
    {
        $this->billingPeriod = $billingPeriod;

        return $this;
    }

    public function getPriceAmount(): string
    {
        return $this->priceAmount;
    }

    public function setPriceAmount(string $priceAmount): static
    {
        $this->priceAmount = $priceAmount;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getMaxRequestsPerMonth(): ?int
    {
        return $this->maxRequestsPerMonth;
    }

    public function setMaxRequestsPerMonth(?int $maxRequestsPerMonth): static
    {
        $this->maxRequestsPerMonth = $maxRequestsPerMonth;

        return $this;
    }

    public function getMaxResponsesPerMonth(): ?int
    {
        return $this->maxResponsesPerMonth;
    }

    public function setMaxResponsesPerMonth(?int $maxResponsesPerMonth): static
    {
        $this->maxResponsesPerMonth = $maxResponsesPerMonth;

        return $this;
    }

    public function getMaxCategories(): ?int
    {
        return $this->maxCategories;
    }

    public function setMaxCategories(?int $maxCategories): static
    {
        $this->maxCategories = $maxCategories;

        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->featured;
    }

    public function setFeatured(bool $featured): static
    {
        $this->featured = $featured;

        return $this;
    }

    public function hasAdvancedStatistics(): bool
    {
        return $this->advancedStatistics;
    }

    public function setAdvancedStatistics(bool $advancedStatistics): static
    {
        $this->advancedStatistics = $advancedStatistics;

        return $this;
    }

    public function getStripePriceId(): ?string
    {
        return $this->stripePriceId;
    }

    public function setStripePriceId(?string $stripePriceId): static
    {
        $this->stripePriceId = $stripePriceId;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}
