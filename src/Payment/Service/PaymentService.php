<?php

declare(strict_types=1);

namespace App\Payment\Service;

use App\Booking\Entity\Booking;
use App\Booking\Enum\BookingStatus;
use App\Notification\Enum\NotificationCategory;
use App\Notification\Service\NotificationService;
use App\Payment\Entity\Payment;
use App\Payment\Enum\PaymentStatus;
use App\Payment\Repository\PaymentRepository;
use App\Payment\Stripe\CheckoutState;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Règlement des prestations : le client paie DIRECTEMENT le professionnel
 * (17/10), jamais TrouveMoi, qui ne prélève aucune commission.
 *
 *  - En ligne (20/10) : par carte sur le compte Stripe du professionnel ou
 *    sur son compte PayPal (BookingCheckoutService). Quand Stripe / PayPal
 *    confirme le paiement, le règlement est enregistré ici et la réservation
 *    est confirmée aussitôt.
 *  - Sur place : le client envoie sa demande, le professionnel la confirme
 *    puis déclare le règlement reçu.
 *
 * Tout règlement reçu reçoit un numéro de facture (facture / reçu PDF
 * téléchargeable par le client et par le professionnel).
 */
final class PaymentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentRepository $payments,
        private readonly NotificationService $notifications,
        private readonly LockFactory $locks,
        private readonly LoggerInterface $logger,
        #[Target('booking')]
        private readonly WorkflowInterface $bookingWorkflow,
    ) {
    }

    /**
     * Le client choisit de régler sur place : sa demande part au
     * professionnel, rien n'est débité. Un paiement en ligne commencé puis
     * abandonné bascule sur ce mode.
     *
     * @throws \InvalidArgumentException si la réservation n'est pas en attente
     *                                   ou si la demande a déjà été envoyée
     */
    public function request(Booking $booking): Payment
    {
        if (BookingStatus::Pending !== $booking->getStatus()) {
            throw new \InvalidArgumentException('Cette réservation n’est plus en attente.');
        }

        $payment = $this->payments->findOneByBooking($booking);
        if (null !== $payment && ($payment->isPaid() || !$payment->isOnline())) {
            throw new \InvalidArgumentException('Votre demande a déjà été envoyée au professionnel.');
        }

        if (null === $payment) {
            $payment = (new Payment())->setBooking($booking);
            $this->entityManager->persist($payment);
        }

        $payment->setAmount($booking->getTotalPrice())
            ->setCurrency($booking->getCurrency())
            ->setChannel(Payment::CHANNEL_ONSITE)
            ->setExternalId(null)
            ->setExternalAccount(null);
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
                ->setCurrency($booking->getCurrency())
                ->setChannel(Payment::CHANNEL_ONSITE);
            $this->entityManager->persist($payment);
        } elseif ($payment->isPaid()) {
            throw new \InvalidArgumentException('Ce règlement est déjà enregistré.');
        }

        $payment->setStatus(PaymentStatus::Paid)->setMethod($method)->setPaidAt(new \DateTimeImmutable());
        $this->settle($payment);

        return $payment;
    }

    /**
     * Stripe ou PayPal confirme le paiement en ligne : règlement enregistré,
     * réservation confirmée, professionnel prévenu. Sans effet si c'est déjà
     * fait (retour du client ET webhook).
     *
     * @return bool le règlement est enregistré
     */
    public function recordOnlinePayment(Payment $payment, CheckoutState $state): bool
    {
        if ($payment->isPaid()) {
            return true;
        }
        if (!$state->paid) {
            return false;
        }

        $expected = (int) round((float) $payment->getAmount() * 100);
        if (null !== $state->amountCents && $state->amountCents !== $expected) {
            $this->logger->error('Paiement {id} : montant reçu {got} ≠ attendu {expected} centimes, réservation non confirmée.', ['id' => (string) $payment->getId(), 'got' => $state->amountCents, 'expected' => $expected]);

            return false;
        }

        $payment->setStatus(PaymentStatus::Paid)
            ->setMethod(Payment::CHANNEL_PAYPAL === $payment->getChannel() ? 'paypal' : 'card')
            ->setTransactionId($state->transactionId)
            ->setCardBrand($state->cardBrand)
            ->setCardLast4($state->cardLast4)
            ->setPaidAt(new \DateTimeImmutable());

        $booking = $payment->getBooking();
        \assert(null !== $booking);
        if ($this->bookingWorkflow->can($booking, 'confirm')) {
            $this->bookingWorkflow->apply($booking, 'confirm', ['paid_online' => true]);
        }
        $this->settle($payment);
        $this->notifyProvider($payment);

        return true;
    }

    /** Numéro de facture (séquence annuelle, verrouillée) puis enregistrement. */
    private function settle(Payment $payment): void
    {
        if (null !== $payment->getInvoiceNumber()) {
            $this->entityManager->flush();

            return;
        }

        $lock = $this->locks->createLock('payment-invoice-number', 30);
        $lock->acquire(true);
        try {
            $year = (int) (new \DateTimeImmutable())->format('Y');
            $last = $this->payments->lastInvoiceNumber($year);
            $next = null !== $last ? (int) substr($last, -5) + 1 : 1;
            $payment->setInvoiceNumber(sprintf('FACT-%d-%05d', $year, $next));
            $this->entityManager->flush();
        } finally {
            $lock->release();
        }
    }

    private function notifyProvider(Payment $payment): void
    {
        $booking = $payment->getBooking();
        $owner = $booking?->getService()?->getProvider()?->getUser();
        if (null === $booking || null === $owner) {
            return;
        }

        $cancelled = \in_array($booking->getStatus(), [BookingStatus::Cancelled, BookingStatus::Refunded], true);
        $this->notifications->notify($owner, NotificationCategory::Booking, 'Paiement reçu', sprintf(
            '%s — réservation #%s, %d pers. : %s € réglés %s sur votre compte. %s',
            $booking->getService()->getTitle(),
            $booking->getReference(),
            $booking->getParticipants(),
            number_format((float) $payment->getAmount(), 2, ',', ' '),
            'paypal' === $payment->getMethod() ? 'par PayPal' : 'par carte (Stripe)',
            $cancelled
                ? 'Cette réservation était annulée : remboursez le client depuis votre compte.'
                : 'La réservation est confirmée ; facture et QR de contrôle sont dans « Réservations ».',
        ));
    }
}
