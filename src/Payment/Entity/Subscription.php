<?php

declare(strict_types=1);

namespace App\Payment\Entity;

use App\Payment\Enum\SubscriptionStatus;
use App\Payment\Repository\SubscriptionRepository;
use App\Provider\Entity\ProviderProfile;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Abonnement d'un professionnel à une offre, avec ses références Stripe
 * (§17.2, §23 du CDC).
 *
 * Une ligne par abonnement souscrit, jamais réécrite en place : changer
 * d'offre ou se réabonner après résiliation crée une nouvelle ligne. C'est
 * ce qui donne « l'historique des événements de paiement » que le CDC
 * demande, et le même principe que LegalDocument (voir CLAUDE.md) — savoir
 * ce qui était vrai à quelle date ne doit jamais se perdre dans un UPDATE.
 */
#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Subscription
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: ProviderProfile::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ProviderProfile $provider = null;

    #[ORM\ManyToOne(targetEntity: SubscriptionPlan::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?SubscriptionPlan $plan = null;

    #[ORM\Column(enumType: SubscriptionStatus::class)]
    private SubscriptionStatus $status = SubscriptionStatus::Incomplete;

    /**
     * Jamais de numéro de carte ni d'autre donnée bancaire ici (§17.2 du
     * CDC) : uniquement les identifiants Stripe nécessaires au suivi.
     */
    #[ORM\Column(length: 80, nullable: true)]
    private ?string $stripeCustomerId = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $stripeSubscriptionId = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $currentPeriodStart = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $currentPeriodEnd = null;

    /** Résiliation demandée, effective seulement à la fin de la période payée. */
    #[ORM\Column(options: ['default' => false])]
    private bool $cancelAtPeriodEnd = false;

    public function getProvider(): ?ProviderProfile
    {
        return $this->provider;
    }

    public function setProvider(?ProviderProfile $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getPlan(): ?SubscriptionPlan
    {
        return $this->plan;
    }

    public function setPlan(?SubscriptionPlan $plan): static
    {
        $this->plan = $plan;

        return $this;
    }

    public function getStatus(): SubscriptionStatus
    {
        return $this->status;
    }

    public function setStatus(SubscriptionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isActive(): bool
    {
        return SubscriptionStatus::Active === $this->status;
    }

    public function getStripeCustomerId(): ?string
    {
        return $this->stripeCustomerId;
    }

    public function setStripeCustomerId(?string $stripeCustomerId): static
    {
        $this->stripeCustomerId = $stripeCustomerId;

        return $this;
    }

    public function getStripeSubscriptionId(): ?string
    {
        return $this->stripeSubscriptionId;
    }

    public function setStripeSubscriptionId(?string $stripeSubscriptionId): static
    {
        $this->stripeSubscriptionId = $stripeSubscriptionId;

        return $this;
    }

    public function getCurrentPeriodStart(): ?\DateTimeImmutable
    {
        return $this->currentPeriodStart;
    }

    public function setCurrentPeriodStart(?\DateTimeImmutable $currentPeriodStart): static
    {
        $this->currentPeriodStart = $currentPeriodStart;

        return $this;
    }

    public function getCurrentPeriodEnd(): ?\DateTimeImmutable
    {
        return $this->currentPeriodEnd;
    }

    public function setCurrentPeriodEnd(?\DateTimeImmutable $currentPeriodEnd): static
    {
        $this->currentPeriodEnd = $currentPeriodEnd;

        return $this;
    }

    public function isCancelAtPeriodEnd(): bool
    {
        return $this->cancelAtPeriodEnd;
    }

    public function setCancelAtPeriodEnd(bool $cancelAtPeriodEnd): static
    {
        $this->cancelAtPeriodEnd = $cancelAtPeriodEnd;

        return $this;
    }
}
