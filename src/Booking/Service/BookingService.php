<?php

declare(strict_types=1);

namespace App\Booking\Service;

use App\Booking\Entity\Booking;
use App\Booking\Entity\BookingItem;
use App\Catalog\Entity\Service;
use App\Catalog\Entity\ServicePackage;
use App\Catalog\Enum\ServiceStatus;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Logique métier de création d'une réservation (modèle « service-as-product »).
 */
final class BookingService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Crée une réservation pour une formule d'une activité publiée.
     *
     * @throws \InvalidArgumentException si la quantité est invalide, si la formule
     *                                   n'appartient pas à l'activité, ou si l'activité
     *                                   n'est pas publiée
     */
    public function createBooking(User $client, Service $service, ServicePackage $package, int $quantity = 1): Booking
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('La quantité doit être au moins 1.');
        }

        if ($package->getService() !== $service) {
            throw new \InvalidArgumentException('Cette formule n\'appartient pas à l\'activité choisie.');
        }

        if (ServiceStatus::Published !== $service->getStatus()) {
            throw new \InvalidArgumentException('Seule une activité publiée peut être réservée.');
        }

        // Snapshot : on fige le libellé et le prix de la formule au moment de l'achat.
        $item = (new BookingItem())
            ->setServicePackage($package)
            ->setLabel($package->getName())
            ->setUnitPrice($package->getPrice())
            ->setQuantity($quantity)
            ->setCurrency($package->getCurrency());

        $booking = (new Booking())
            ->setClient($client)
            ->setService($service)
            ->setCurrency($package->getCurrency())
            ->setTotalPrice($this->multiply($package->getPrice(), $quantity));
        $booking->addItem($item);

        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        return $booking;
    }

    /**
     * Multiplie un montant décimal (ex. "49.90") par une quantité entière sans
     * passer par les float : calcul en centimes pour éviter les erreurs d'arrondi.
     */
    private function multiply(string $amount, int $quantity): string
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
        $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);
        $cents = ((int) $whole * 100 + (int) $fraction) * $quantity;

        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
