<?php

declare(strict_types=1);

namespace App\Payment\Entity;

use App\Booking\Entity\Booking;
use App\Payment\Enum\PaymentStatus;
use App\Payment\Repository\PaymentRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Paiement d'une réservation. Le montant est figé (decimal) et le statut suit
 * le résultat du prestataire de paiement.
 */
#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Payment
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\OneToOne(targetEntity: Booking::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?Booking $booking = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $amount;

    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    private string $currency = 'EUR';

    #[ORM\Column(enumType: PaymentStatus::class)]
    private PaymentStatus $status = PaymentStatus::Pending;

    /**
     * Référence de transaction renvoyée par le prestataire (null tant qu'aucun
     * débit n'a réussi).
     */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $reference = null;

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }

    public function setBooking(?Booking $booking): static
    {
        $this->booking = $booking;

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

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function setStatus(PaymentStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }
}
