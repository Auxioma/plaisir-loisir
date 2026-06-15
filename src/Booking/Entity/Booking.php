<?php

declare(strict_types=1);

namespace App\Booking\Entity;

use App\Booking\Enum\BookingStatus;
use App\Booking\Repository\BookingRepository;
use App\Catalog\Entity\Service;
use App\Shared\Doctrine\SoftDeletableTrait;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Réservation passée par un client pour une activité.
 *
 * Document financier : on conserve l'historique (soft delete) et le prix est figé
 * dans les lignes (BookingItem) au moment de l'achat.
 */
#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\Index(columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class Booking
{
    use UlidIdentifierTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $client = null;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Service $service = null;

    #[ORM\Column(enumType: BookingStatus::class)]
    private BookingStatus $status = BookingStatus::Pending;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $totalPrice = '0.00';

    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    private string $currency = 'EUR';

    /**
     * @var Collection<int, BookingItem>
     */
    #[ORM\OneToMany(targetEntity: BookingItem::class, mappedBy: 'booking', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getClient(): ?User
    {
        return $this->client;
    }

    public function setClient(?User $client): static
    {
        $this->client = $client;

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

    public function getStatus(): BookingStatus
    {
        return $this->status;
    }

    public function setStatus(BookingStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getTotalPrice(): string
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(string $totalPrice): static
    {
        $this->totalPrice = $totalPrice;

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

    /**
     * @return Collection<int, BookingItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(BookingItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setBooking($this);
        }

        return $this;
    }

    public function removeItem(BookingItem $item): static
    {
        if ($this->items->removeElement($item) && $item->getBooking() === $this) {
            $item->setBooking(null);
        }

        return $this;
    }

    /**
     * Pont pour Symfony Workflow : expose le statut sous forme de chaîne.
     */
    public function getMarking(): string
    {
        return $this->status->value;
    }

    /**
     * Pont pour Symfony Workflow : applique un marquage (place) sous forme de chaîne.
     *
     * @param array<string, mixed> $context
     */
    public function setMarking(string $marking, array $context = []): void
    {
        $this->status = BookingStatus::from($marking);
    }
}
