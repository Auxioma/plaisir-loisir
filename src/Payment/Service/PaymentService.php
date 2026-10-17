<?php

declare(strict_types=1);

namespace App\Payment\Service;

use App\Booking\Entity\Booking;
use App\Booking\Enum\BookingStatus;
use App\Payment\Entity\Payment;
use App\Payment\Enum\PaymentStatus;
use App\Payment\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Règlement des prestations (17/10, demande du client) : le client paie
 * DIRECTEMENT le professionnel, par les moyens que celui-ci a configurés
 * (lien de paiement, PayPal, virement, sur place). TrouveMoi n'encaisse
 * jamais le prix d'une prestation : ce service ne fait que suivre le
 * règlement attendu, puis reçu, que le professionnel déclare.
 */
final class PaymentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentRepository $payments,
    ) {
    }

    /**
     * Le client envoie sa demande de réservation : le règlement est attendu
     * par le professionnel, rien n'est débité.
     *
     * @throws \InvalidArgumentException si la réservation n'est pas en attente
     *                                   ou si la demande a déjà été envoyée
     */
    public function request(Booking $booking): Payment
    {
        if (BookingStatus::Pending !== $booking->getStatus()) {
            throw new \InvalidArgumentException('Cette réservation n’est plus en attente.');
        }

        if (null !== $this->payments->findOneByBooking($booking)) {
            throw new \InvalidArgumentException('Votre demande a déjà été envoyée au professionnel.');
        }

        $payment = (new Payment())
            ->setBooking($booking)
            ->setAmount($booking->getTotalPrice())
            ->setCurrency($booking->getCurrency());

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $payment;
    }

    /**
     * Le professionnel déclare avoir reçu le règlement de son client.
     *
     * @throws \InvalidArgumentException réservation annulée, moyen inconnu ou
     *                                   règlement déjà déclaré
     */
    public function markPaid(Booking $booking, string $method): Payment
    {
        if (\in_array($booking->getStatus(), [BookingStatus::Cancelled, BookingStatus::Refunded], true)) {
            throw new \InvalidArgumentException('Cette réservation est annulée.');
        }

        if (!isset(Payment::METHODS[$method])) {
            throw new \InvalidArgumentException('Choisissez le moyen de paiement utilisé par le client.');
        }

        $payment = $this->payments->findOneByBooking($booking);
        if (null === $payment) {
            $payment = (new Payment())
                ->setBooking($booking)
                ->setAmount($booking->getTotalPrice())
                ->setCurrency($booking->getCurrency());
            $this->entityManager->persist($payment);
        } elseif (PaymentStatus::Paid === $payment->getStatus()) {
            throw new \InvalidArgumentException('Ce règlement est déjà enregistré.');
        }

        $payment->setStatus(PaymentStatus::Paid)->setMethod($method)->setPaidAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $payment;
    }
}
