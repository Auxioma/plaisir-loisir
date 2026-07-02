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

    /**
     * Confirme un paiement à partir de la référence de session renvoyée par le
     * webhook Stripe (« checkout.session.completed ») : marque le paiement réglé et
     * confirme la réservation.
     *
     * La méthode est **idempotente** : Stripe pouvant livrer le même événement
     * plusieurs fois, un paiement déjà réglé est simplement ignoré.
     *
     * @throws \InvalidArgumentException si aucun paiement ne correspond à la référence
     *                                   ou si le paiement n'est pas dans un état attendu
     */
    public function confirmBySessionReference(string $sessionReference): void
    {
        $payment = $this->payments->findOneByReference($sessionReference);
        if (null === $payment) {
            throw new \InvalidArgumentException('Aucun paiement ne correspond à cette session Stripe.');
        }

        if (PaymentStatus::Paid === $payment->getStatus()) {
            return; // déjà confirmé : on ne fait rien (idempotence)
        }

        if (PaymentStatus::Pending !== $payment->getStatus()) {
            throw new \InvalidArgumentException('Ce paiement n\'est pas en attente de confirmation.');
        }

        $payment->setStatus(PaymentStatus::Paid);

        $booking = $payment->getBooking();
        if (null !== $booking) {
            $workflow = $this->workflowRegistry->get($booking, 'booking');
            if ($workflow->can($booking, 'confirm')) {
                $workflow->apply($booking, 'confirm');
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Rembourse un paiement réglé et bascule la réservation en « remboursée ».
     *
     * @throws \InvalidArgumentException si le paiement n'est pas réglé, s'il n'a pas
     *                                   de réservation, ou si elle n'est pas remboursable
     */
    public function refund(Payment $payment): void
    {
        if (PaymentStatus::Paid !== $payment->getStatus()) {
            throw new \InvalidArgumentException('Seul un paiement réglé peut être remboursé.');
        }

        $booking = $payment->getBooking();
        if (null === $booking) {
            throw new \InvalidArgumentException('Ce paiement n\'est rattaché à aucune réservation.');
        }

        $workflow = $this->workflowRegistry->get($booking, 'booking');
        if (!$workflow->can($booking, 'refund')) {
            throw new \InvalidArgumentException('La réservation n\'est pas dans un état remboursable.');
        }

        $workflow->apply($booking, 'refund');
        $payment->setStatus(PaymentStatus::Refunded);
        $this->entityManager->flush();
    }
}
