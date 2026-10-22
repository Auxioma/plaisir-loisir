<?php

declare(strict_types=1);

namespace App\Booking\Presenter;

use App\Availability\Repository\AvailabilityRepository;
use App\Booking\Entity\Booking;
use App\Booking\Enum\BookingStatus;
use App\Booking\Service\BookingQrCode;
use App\Legal\Repository\CompanyIdentityRepository;
use App\Payment\Entity\Payment;
use App\Payment\Repository\PaymentRepository;

/**
 * Données communes aux écrans « Réservation confirmée » (client) et
 * « Réservation payée » (professionnel) — maquettes du 20/10 : transaction,
 * réservation, paiement, facture, QR de réservation, suivi.
 */
final class BookingPaymentPresenter
{
    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly AvailabilityRepository $availabilities,
        private readonly CompanyIdentityRepository $companies,
        private readonly BookingQrCode $qrCode,
    ) {
    }

    /** @return array<string, mixed> */
    public function present(Booking $booking): array
    {
        $service = $booking->getService();
        $provider = $service?->getProvider();
        $payment = $this->payments->findOneByBooking($booking);
        $cancelled = \in_array($booking->getStatus(), [BookingStatus::Cancelled, BookingStatus::Refunded], true);

        return [
            'booking' => $booking,
            'service' => $service,
            'provider' => $provider,
            'company' => null !== $provider ? $this->companies->findOneByProvider($provider) : null,
            'payment' => $payment,
            'paid' => null !== $payment && $payment->isPaid(),
            'cancelled' => $cancelled,
            'ends_at' => $this->endsAt($booking),
            'method_label' => self::methodLabel($payment),
            'card_label' => null !== $payment && null !== $payment->getCardLast4()
                ? sprintf('%s **** %s', ucfirst((string) $payment->getCardBrand()), $payment->getCardLast4())
                : null,
            'qr' => $cancelled ? null : $this->qrCode->dataUri($booking),
            'timeline' => $this->timeline($booking, $payment),
        ];
    }

    public static function methodLabel(?Payment $payment): string
    {
        return match (true) {
            null === $payment => 'À convenir avec l’organisateur',
            'gift' === $payment->getMethod() => 'Bon cadeau',
            'card' === $payment->getMethod() => 'Carte bancaire',
            null !== $payment->getMethodLabel() => $payment->getMethodLabel(),
            Payment::CHANNEL_STRIPE === $payment->getChannel() => 'Carte bancaire (non finalisé)',
            Payment::CHANNEL_PAYPAL === $payment->getChannel() => 'PayPal (non finalisé)',
            default => 'Sur place (espèces)',
        };
    }

    private function endsAt(Booking $booking): ?\DateTimeImmutable
    {
        $start = $booking->getStartsAt();
        $service = $booking->getService();
        if (null === $start || null === $service) {
            return null;
        }

        $slot = $this->availabilities->findOneByServiceAndStart($service, $start);
        if (null !== $slot) {
            return $slot->getEndsAt();
        }

        return null !== $service->getDurationMinutes() ? $start->modify(sprintf('+%d minutes', $service->getDurationMinutes())) : null;
    }

    /**
     * Suivi du paiement (maquette prestataire) : étapes réellement franchies.
     *
     * @return list<array{label: string, text: string, at: ?\DateTimeImmutable, done: bool}>
     */
    private function timeline(Booking $booking, ?Payment $payment): array
    {
        $paid = null !== $payment && $payment->isPaid();
        $online = null !== $payment && $payment->isOnline();
        $gift = null !== $payment && 'gift' === $payment->getChannel();
        $confirmed = \in_array($booking->getStatus(), [BookingStatus::Confirmed, BookingStatus::InProgress, BookingStatus::Completed], true);

        $steps = [['label' => 'Réservation effectuée', 'text' => 'Le client a effectué une réservation en ligne.', 'at' => $booking->getCreatedAt(), 'done' => true]];
        if ($gift) {
            $steps[] = ['label' => 'Bon cadeau vérifié', 'text' => 'Le bon a été payé lors de son achat auprès du prestataire et correspond à cette activité.', 'at' => $payment?->getPaidAt(), 'done' => $paid];
            $steps[] = ['label' => 'Réservation confirmée', 'text' => 'La réservation est confirmée sans nouveau débit.', 'at' => $paid ? $payment?->getPaidAt() : null, 'done' => $confirmed];
        } elseif ($online) {
            $steps[] = ['label' => 'Paiement validé', 'text' => Payment::CHANNEL_PAYPAL === $payment->getChannel() ? 'Le paiement a été confirmé par PayPal.' : 'Le paiement a été confirmé par Stripe.', 'at' => $payment->getPaidAt(), 'done' => $paid];
            $steps[] = ['label' => 'Paiement reçu par le prestataire', 'text' => Payment::CHANNEL_PAYPAL === $payment->getChannel() ? 'Le montant a été versé directement sur votre compte PayPal.' : 'Le montant a été versé directement sur votre compte Stripe.', 'at' => $payment->getPaidAt(), 'done' => $paid];
            $steps[] = ['label' => 'Réservation confirmée', 'text' => 'La réservation a été confirmée automatiquement après le paiement.', 'at' => $paid ? $payment->getPaidAt() : null, 'done' => $confirmed];
        } else {
            $steps[] = ['label' => 'Réservation confirmée', 'text' => 'Vous avez confirmé la réservation.', 'at' => null, 'done' => $confirmed];
            $steps[] = ['label' => 'Paiement reçu par le prestataire', 'text' => 'Vous avez déclaré le règlement reçu sur place.', 'at' => $payment?->getPaidAt(), 'done' => $paid];
        }
        $steps[] = ['label' => 'Entrée validée', 'text' => 'Le QR de réservation a été contrôlé le jour de l’activité.', 'at' => $booking->getCheckedInAt(), 'done' => null !== $booking->getCheckedInAt()];

        return $steps;
    }
}
