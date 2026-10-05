<?php

declare(strict_types=1);

namespace App\Booking\Service;

use App\Availability\Entity\Availability;
use App\Booking\Entity\Booking;
use App\Booking\Entity\BookingItem;
use App\Catalog\Entity\Service;
use App\Catalog\Entity\ServicePackage;
use App\Catalog\Enum\PricingUnit;
use App\Catalog\Enum\PromotionKind;
use App\Catalog\Enum\ServiceStatus;
use App\Catalog\Repository\PromotionRepository;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Logique métier de création d'une réservation (modèle « service-as-product »).
 */
final class BookingService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ?PromotionRepository $promotions = null,
    ) {
    }

    /**
     * Crée une réservation pour une formule d'une activité publiée.
     *
     * @throws \InvalidArgumentException si la quantité est invalide, si la formule
     *                                   n'appartient pas à l'activité, ou si l'activité
     *                                   n'est pas publiée
     */
    public function createBooking(User $client, Service $service, ServicePackage $package, int $quantity = 1, ?\DateTimeImmutable $startsAt = null, ?Availability $slot = null): Booking
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

        // Offre du moment (04/10) : une réduction en % en cours sur l'activité
        // s'applique au prix unitaire — la page « Offres du moment » affiche
        // le prix remisé, la réservation doit le facturer.
        $unitPrice = $package->getPrice();
        $label = $package->getName();
        $promotion = $this->promotions?->findRunningForService($service);
        if (null !== $promotion && PromotionKind::Reduction === $promotion->getKind() && ($promotion->getDiscountPercent() ?? 0) > 0) {
            $unitPrice = self::discount($unitPrice, (int) $promotion->getDiscountPercent());
            $label .= sprintf(' (offre -%d %%)', $promotion->getDiscountPercent());
        }

        // Tarif « par groupe » ou « forfait » (05/10) : un seul prix pour le
        // groupe, quel que soit le nombre de participants.
        $perPerson = PricingUnit::PerPerson === $package->getPricingUnit();

        // Snapshot : on fige le libellé et le prix de la formule au moment de l'achat.
        $item = (new BookingItem())
            ->setServicePackage($package)
            ->setLabel($label)
            ->setUnitPrice($unitPrice)
            ->setQuantity($perPerson ? $quantity : 1)
            ->setCurrency($package->getCurrency());

        if (null !== $startsAt && $startsAt < new \DateTimeImmutable()) {
            throw new \InvalidArgumentException('Choisissez une date et une heure à venir.');
        }

        // Places prises sur le créneau choisi (contrôle de capacité par l'entité).
        if (null !== $slot) {
            if ($slot->getService() !== $service) {
                throw new \InvalidArgumentException('Ce créneau n\'appartient pas à l\'activité choisie.');
            }
            $slot->reserve($quantity);
            $startsAt = $slot->getStartsAt();
        }

        $booking = (new Booking())
            ->setClient($client)
            ->setService($service)
            ->setParticipants($quantity)
            ->setStartsAt($startsAt)
            ->setCurrency($package->getCurrency())
            ->setTotalPrice($this->multiply($unitPrice, $perPerson ? $quantity : 1));
        $booking->addItem($item);

        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        return $booking;
    }

    /**
     * Multiplie un montant décimal (ex. "49.90") par une quantité entière sans
     * passer par les float : calcul en centimes pour éviter les erreurs d'arrondi.
     */
    /** Prix remisé de $percent %, arrondi au centime (calcul en centimes entiers). */
    public static function discount(string $amount, int $percent): string
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
        $cents = (int) $whole * 100 + (int) substr(str_pad($fraction, 2, '0'), 0, 2);
        $cents = intdiv($cents * (100 - max(0, min(100, $percent))) + 50, 100);

        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    private function multiply(string $amount, int $quantity): string
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
        $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);
        $cents = ((int) $whole * 100 + (int) $fraction) * $quantity;

        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
