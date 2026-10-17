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
 * Règlement d'une réservation, effectué DIRECTEMENT entre le client et le
 * professionnel (17/10, demande du client) : aucun paiement de prestation ne
 * transite par TrouveMoi. La ligne naît « en attente » quand le client envoie
 * sa demande ; le professionnel la déclare « réglée » quand il a reçu
 * l'argent, avec le moyen utilisé. Le montant est figé (decimal).
 */
#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Payment
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    /** Moyens par lesquels un client règle le professionnel. */
    public const METHODS = [
        'onsite' => 'Sur place',
        'transfer' => 'Virement',
        'paypal' => 'PayPal',
        'link' => 'Lien de paiement',
        'card' => 'Carte bancaire',
        'cash' => 'Espèces',
        'other' => 'Autre',
    ];

    #[ORM\OneToOne(targetEntity: Booking::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?Booking $booking = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $amount;

    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    private string $currency = 'EUR';

    #[ORM\Column(enumType: PaymentStatus::class)]
    private PaymentStatus $status = PaymentStatus::Pending;

    /** Référence libre (ancienne référence de transaction, n° de virement…). */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $reference = null;

    /** Moyen par lequel le professionnel a été réglé (voir METHODS) ; null tant qu'il ne l'a pas déclaré. */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $method = null;

    /** Date à laquelle le professionnel a déclaré le règlement reçu. */
    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

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

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(?string $method): static
    {
        $this->method = null === $method || isset(self::METHODS[$method]) ? $method : 'other';

        return $this;
    }

    public function getMethodLabel(): ?string
    {
        return null !== $this->method ? self::METHODS[$this->method] : null;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;

        return $this;
    }
}
