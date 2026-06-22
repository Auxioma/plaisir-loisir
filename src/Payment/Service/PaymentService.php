<?php

declare(strict_types=1);

namespace App\Payment\Service;

use App\Booking\Entity\Booking;
use App\Booking\Enum\BookingStatus;
use App\Payment\Entity\Payment;
use App\Payment\Enum\PaymentStatus;
use App\Payment\Processor\PaymentProcessor;
use App\Payment\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\Registry;

/**
 * Logique métier du paiement. S'appuie sur l'interface PaymentProcessor (mock
 * aujourd'hui, Stripe demain) ; un paiement réussi confirme la réservation.
 */
final class PaymentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentProcessor $processor,
        private readonly PaymentRepository $payments,
        private readonly Registry $workflowRegistry,
    ) {
    }

    /**
     * @throws \InvalidArgumentException si la réservation n'est pas en attente
     *                                   ou si elle a déjà un paiement
     */
    public function pay(Booking $booking): Payment
    {
        if (BookingStatus::Pending !== $booking->getStatus()) {
            throw new \InvalidArgumentException('Seule une réservation en attente peut être payée.');
        }

        if (null !== $this->payments->findOneByBooking($booking)) {
            throw new \InvalidArgumentException('Cette réservation a déjà un paiement.');
        }

        $payment = (new Payment())
            ->setBooking($booking)
            ->setAmount($booking->getTotalPrice())
            ->setCurrency($booking->getCurrency());

        $reference = $this->processor->charge($payment);

        if (null !== $reference) {
            $payment->setReference($reference)->setStatus(PaymentStatus::Paid);
            // Le paiement réussi confirme la réservation (pending -> confirmed).
            $this->workflowRegistry->get($booking, 'booking')->apply($booking, 'confirm');
        } else {
            $payment->setStatus(PaymentStatus::Failed);
        }

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $payment;
    }
}
