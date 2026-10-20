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
 * transite par TrouveMoi. Le montant est figé (decimal).
 *
 * Deux canaux (20/10) :
 *  - en ligne (`stripe`, `paypal`) : le client paie à la réservation sur le
 *    compte Stripe ou PayPal du professionnel ; la ligne passe « réglée »
 *    quand Stripe / PayPal confirme le paiement, et un numéro de facture lui
 *    est attribué ;
 *  - sur place (`onsite`, ou null pour les demandes antérieures) : la ligne
 *    naît « en attente » à l'envoi de la demande, le professionnel la déclare
 *    « réglée » quand il a reçu l'argent, avec le moyen utilisé.
 */
#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_payment_external_id', columns: ['external_id'])]
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

    public const CHANNEL_STRIPE = 'stripe';
    public const CHANNEL_PAYPAL = 'paypal';
    public const CHANNEL_ONSITE = 'onsite';

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

    /** Date du règlement : confirmé par Stripe / PayPal, ou déclaré par le professionnel. */
    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    /** Canal choisi par le client (CHANNEL_*) ; null = demande antérieure au 20/10 (sur place). */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $channel = null;

    /** Session Stripe Checkout (« cs_… ») ou commande PayPal en cours. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalId = null;

    /** Compte Stripe du professionnel sur lequel la session a été créée. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalAccount = null;

    /** Transaction confirmée : PaymentIntent Stripe (« pi_… ») ou capture PayPal. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $transactionId = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $cardBrand = null;

    #[ORM\Column(length: 4, nullable: true)]
    private ?string $cardLast4 = null;

    /** Numéro de la facture / du reçu, attribué au règlement (FACT-AAAA-NNNNN). */
    #[ORM\Column(length: 30, unique: true, nullable: true)]
    private ?string $invoiceNumber = null;

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

    public function getChannel(): ?string
    {
        return $this->channel;
    }

    public function setChannel(?string $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    public function isOnline(): bool
    {
        return \in_array($this->channel, [self::CHANNEL_STRIPE, self::CHANNEL_PAYPAL], true);
    }

    public function isPaid(): bool
    {
        return PaymentStatus::Paid === $this->status;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): static
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getExternalAccount(): ?string
    {
        return $this->externalAccount;
    }

    public function setExternalAccount(?string $externalAccount): static
    {
        $this->externalAccount = $externalAccount;

        return $this;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function setTransactionId(?string $transactionId): static
    {
        $this->transactionId = $transactionId;

        return $this;
    }

    public function getCardBrand(): ?string
    {
        return $this->cardBrand;
    }

    public function setCardBrand(?string $cardBrand): static
    {
        $this->cardBrand = $cardBrand;

        return $this;
    }

    public function getCardLast4(): ?string
    {
        return $this->cardLast4;
    }

    public function setCardLast4(?string $cardLast4): static
    {
        $this->cardLast4 = $cardLast4;

        return $this;
    }

    public function getInvoiceNumber(): ?string
    {
        return $this->invoiceNumber;
    }

    public function setInvoiceNumber(?string $invoiceNumber): static
    {
        $this->invoiceNumber = $invoiceNumber;

        return $this;
    }
}
