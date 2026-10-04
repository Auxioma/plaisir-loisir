<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use App\Catalog\Repository\GiftCardRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Bon cadeau acheté sur /cadeaux (maquette bons_cadeaux.jpeg, 04/10).
 *
 * Valable 12 mois à compter du paiement. Il porte un montant (celui de
 * l'activité choisie, figé à l'achat, ou un montant libre) et un code à
 * communiquer au bénéficiaire. Le libellé et le montant sont des snapshots :
 * modifier l'activité ensuite ne change pas un bon déjà vendu.
 */
#[ORM\Entity(repositoryClass: GiftCardRepository::class)]
#[ORM\Index(columns: ['checkout_reference'])]
#[ORM\HasLifecycleCallbacks]
class GiftCard
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_REDEEMED = 'redeemed';
    public const STATUS_CANCELLED = 'cancelled';

    public const DELIVERY_EMAIL = 'email';
    public const DELIVERY_PRINT = 'print';
    public const DELIVERY_POSTAL = 'postal';

    #[ORM\Column(length: 20, unique: true)]
    private string $code = '';

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Service $service = null;

    #[ORM\Column(length: 180)]
    private string $label = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $amount = '0.00';

    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    private string $currency = 'EUR';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $buyer = null;

    #[ORM\Column(length: 200)]
    private string $buyerName = '';

    #[ORM\Column(length: 180)]
    private string $buyerEmail = '';

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $buyerPhone = null;

    #[ORM\Column(length: 120)]
    private string $recipientName = '';

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $recipientEmail = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(length: 10, options: ['default' => self::DELIVERY_EMAIL])]
    private string $delivery = self::DELIVERY_EMAIL;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $postalAddress = null;

    #[ORM\Column(length: 12, options: ['default' => self::STATUS_PENDING])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $checkoutReference = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    public function __toString(): string
    {
        return $this->code;
    }

    public function isPaid(): bool
    {
        return \in_array($this->status, [self::STATUS_PAID, self::STATUS_REDEEMED], true);
    }

    public function markPaid(\DateTimeImmutable $at): static
    {
        $this->status = self::STATUS_PAID;
        $this->paidAt = $at;
        $this->expiresAt = $at->modify('+12 months');

        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

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

    public function getBuyer(): ?User
    {
        return $this->buyer;
    }

    public function setBuyer(?User $buyer): static
    {
        $this->buyer = $buyer;

        return $this;
    }

    public function getBuyerName(): string
    {
        return $this->buyerName;
    }

    public function setBuyerName(string $buyerName): static
    {
        $this->buyerName = $buyerName;

        return $this;
    }

    public function getBuyerEmail(): string
    {
        return $this->buyerEmail;
    }

    public function setBuyerEmail(string $buyerEmail): static
    {
        $this->buyerEmail = $buyerEmail;

        return $this;
    }

    public function getBuyerPhone(): ?string
    {
        return $this->buyerPhone;
    }

    public function setBuyerPhone(?string $buyerPhone): static
    {
        $this->buyerPhone = $buyerPhone;

        return $this;
    }

    public function getRecipientName(): string
    {
        return $this->recipientName;
    }

    public function setRecipientName(string $recipientName): static
    {
        $this->recipientName = $recipientName;

        return $this;
    }

    public function getRecipientEmail(): ?string
    {
        return $this->recipientEmail;
    }

    public function setRecipientEmail(?string $recipientEmail): static
    {
        $this->recipientEmail = $recipientEmail;

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

    public function getDelivery(): string
    {
        return $this->delivery;
    }

    public function setDelivery(string $delivery): static
    {
        $this->delivery = $delivery;

        return $this;
    }

    public function getPostalAddress(): ?string
    {
        return $this->postalAddress;
    }

    public function setPostalAddress(?string $postalAddress): static
    {
        $this->postalAddress = $postalAddress;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCheckoutReference(): ?string
    {
        return $this->checkoutReference;
    }

    public function setCheckoutReference(?string $checkoutReference): static
    {
        $this->checkoutReference = $checkoutReference;

        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
