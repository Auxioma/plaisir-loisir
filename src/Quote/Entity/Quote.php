<?php

declare(strict_types=1);

namespace App\Quote\Entity;

use App\Provider\Entity\ProviderProfile;
use App\Quote\Enum\QuoteStatus;
use App\Quote\Repository\QuoteRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Devis proposé par un annonceur en réponse à une demande. Un devis par annonceur
 * et par demande.
 */
#[ORM\Entity(repositoryClass: QuoteRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_quote_request_provider', columns: ['service_request_id', 'provider_id'])]
#[ORM\HasLifecycleCallbacks]
class Quote
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: ServiceRequest::class, inversedBy: 'quotes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ServiceRequest $serviceRequest = null;

    #[ORM\ManyToOne(targetEntity: ProviderProfile::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ProviderProfile $provider = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $amount;

    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    private string $currency = 'EUR';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column(enumType: QuoteStatus::class)]
    private QuoteStatus $status = QuoteStatus::Pending;

    public function getServiceRequest(): ?ServiceRequest
    {
        return $this->serviceRequest;
    }

    public function setServiceRequest(?ServiceRequest $serviceRequest): static
    {
        $this->serviceRequest = $serviceRequest;

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

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

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

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getStatus(): QuoteStatus
    {
        return $this->status;
    }

    public function accept(): void
    {
        $this->status = QuoteStatus::Accepted;
    }

    public function decline(): void
    {
        $this->status = QuoteStatus::Declined;
    }
}
