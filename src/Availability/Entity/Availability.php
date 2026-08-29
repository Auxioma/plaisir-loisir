<?php

declare(strict_types=1);

namespace App\Availability\Entity;

use App\Availability\Repository\AvailabilityRepository;
use App\Catalog\Entity\Service;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Créneau horaire réservable d'une activité (modèle "calendar").
 * Suit le nombre de places réservées par rapport à la capacité.
 */
#[ORM\Entity(repositoryClass: AvailabilityRepository::class)]
#[ORM\Index(columns: ['starts_at'])]
#[ORM\HasLifecycleCallbacks]
class Availability
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Service $service = null;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $startsAt;

    // Un creneau qui se ferme avant de s'ouvrir n'exclut rien et ne se voit
    // pas : la contrainte le refuse a la saisie plutot que de laisser une
    // ligne inerte en base. Elle vit sur l'entite et non sur le formulaire du
    // back-office, pour valoir aussi le jour ou un prestataire saisira ses
    // propres disponibilites.
    #[ORM\Column(type: 'datetimetz_immutable')]
    #[Assert\GreaterThan(propertyPath: 'startsAt', message: 'La fermeture doit venir apres l\'ouverture.')]
    private \DateTimeImmutable $endsAt;

    #[ORM\Column]
    #[Assert\Positive(message: 'Un creneau sans place ne sert a rien : indiquez au moins une place.')]
    private int $capacity;

    #[ORM\Column(options: ['default' => 0])]
    private int $booked = 0;

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;

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

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): static
    {
        $this->capacity = $capacity;

        return $this;
    }

    public function getBooked(): int
    {
        return $this->booked;
    }

    public function getRemainingSeats(): int
    {
        return max(0, $this->capacity - $this->booked);
    }

    public function isBookable(): bool
    {
        return $this->getRemainingSeats() > 0;
    }

    /**
     * Réserve des places sur le créneau.
     *
     * @throws \InvalidArgumentException si le nombre est invalide ou dépasse les places restantes
     */
    public function reserve(int $seats): void
    {
        if ($seats < 1) {
            throw new \InvalidArgumentException('Le nombre de places doit être au moins 1.');
        }

        if ($seats > $this->getRemainingSeats()) {
            throw new \InvalidArgumentException('Pas assez de places disponibles sur ce créneau.');
        }

        $this->booked += $seats;
    }

    /**
     * Libère des places (annulation) sans descendre sous zéro.
     */
    public function release(int $seats): void
    {
        $this->booked = max(0, $this->booked - $seats);
    }
}
