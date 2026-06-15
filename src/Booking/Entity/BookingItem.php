<?php

declare(strict_types=1);

namespace App\Booking\Entity;

use App\Booking\Repository\BookingItemRepository;
use App\Catalog\Entity\ServicePackage;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ligne d'une réservation. Fige le libellé et le prix unitaire au moment de l'achat
 * (snapshot) : la ligne ne suit pas les modifications ultérieures de la formule.
 */
#[ORM\Entity(repositoryClass: BookingItemRepository::class)]
#[ORM\HasLifecycleCallbacks]
class BookingItem
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: Booking::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Booking $booking = null;

    /**
     * Référence d'origine, conservée à titre indicatif. Mise à NULL si la formule
     * est supprimée : le snapshot (label/unitPrice) reste, lui, intact.
     */
    #[ORM\ManyToOne(targetEntity: ServicePackage::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ServicePackage $servicePackage = null;

    #[ORM\Column(length: 150)]
    private string $label;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $unitPrice;

    #[ORM\Column(options: ['default' => 1])]
    private int $quantity = 1;

    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    private string $currency = 'EUR';

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }

    public function setBooking(?Booking $booking): static
    {
        $this->booking = $booking;

        return $this;
    }

    public function getServicePackage(): ?ServicePackage
    {
        return $this->servicePackage;
    }

    public function setServicePackage(?ServicePackage $servicePackage): static
    {
        $this->servicePackage = $servicePackage;

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

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

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
}
