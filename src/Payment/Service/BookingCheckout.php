<?php

declare(strict_types=1);

namespace App\Payment\Service;

use App\Booking\Entity\Booking;
use App\Payment\Enum\PaymentStatus;
use App\Payment\Stripe\StripeCheckoutService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Paiement d'une réservation depuis le tunnel (03/10).
 *
 * Avec une vraie clé Stripe (`sk_test_…` / `sk_live_…`), le client part sur
 * la page de paiement hébergée de Stripe ; la confirmation arrive par le
 * webhook. Sans clé (développement, démo), le processeur de paiement simulé
 * (MockPaymentProcessor) règle immédiatement et confirme la réservation.
 */
final class BookingCheckout
{
    /**
     * @param \Closure(): StripeCheckoutService $stripe construit seulement si
     *                                                  une clé Stripe est configurée
     *                                                  (le client Stripe refuse une clé vide)
     */
    public function __construct(
        #[AutowireServiceClosure(StripeCheckoutService::class)]
        private readonly \Closure $stripe,
        private readonly PaymentService $payments,
        private readonly UrlGeneratorInterface $urls,
        #[Autowire('%env(STRIPE_SECRET_KEY)%')]
        private readonly string $stripeSecretKey,
    ) {
    }

    public function usesStripe(): bool
    {
        return str_starts_with($this->stripeSecretKey, 'sk_');
    }

    /**
     * @return string URL vers laquelle rediriger le client
     *
     * @throws \InvalidArgumentException réservation non payable
     */
    public function start(Booking $booking): string
    {
        if ($this->usesStripe()) {
            return ($this->stripe)()->startCheckout($booking);
        }

        $payment = $this->payments->pay($booking);

        return $this->urls->generate('app_booking_confirmation', ['id' => (string) $booking->getId(), 'echec' => PaymentStatus::Paid === $payment->getStatus() ? null : 1]);
    }
}
