<?php

declare(strict_types=1);

namespace App\Availability\Service;

use App\Availability\Entity\Availability;
use App\Catalog\Entity\Service;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Logique métier des créneaux : création et réservation de places.
 */
final class AvailabilityService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws \InvalidArgumentException si la fin précède le début ou si la capacité est invalide
     */
    public function createSlot(Service $service, \DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt, int $capacity): Availability
    {
        if ($endsAt <= $startsAt) {
            throw new \InvalidArgumentException('La fin du créneau doit être après son début.');
        }

        if ($capacity < 1) {
            throw new \InvalidArgumentException('La capacité doit être au moins 1.');
        }

        $slot = (new Availability())
            ->setService($service)
            ->setStartsAt($startsAt)
            ->setEndsAt($endsAt)
            ->setCapacity($capacity);

        $this->entityManager->persist($slot);
        $this->entityManager->flush();

        return $slot;
    }

    /**
     * Réserve des places sur un créneau (la capacité est contrôlée par l'entité).
     */
    public function reserve(Availability $slot, int $seats): void
    {
        $slot->reserve($seats);
        $this->entityManager->flush();
    }
}
