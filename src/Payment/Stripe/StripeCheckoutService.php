<?php

declare(strict_types=1);

namespace App\Payment\Stripe;

use App\Booking\Entity\Booking;
use App\Booking\Enum\BookingStatus;
use App\Payment\Entity\Payment;
use App\Payment\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Démarre le paiement d'une réservation via Stripe Checkout.
 *
 * Rappel du flux (asynchrone) : ce service crée un {@see Payment} « en attente »
 * et une session Checkout, puis renvoie l'URL de paiement. La confirmation, elle,
 * arrive plus tard par le webhook (voir {@see \App\Payment\Service\PaymentService::confirmBySessionReference()}).
 */
final class StripeCheckoutService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentRepository $payments,
        private readonly CheckoutGateway $gateway,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Crée la session de paiement et renvoie l'URL vers laquelle rediriger le client.
     *
     * @throws \InvalidArgumentException si la réservation n'est pas en attente
     *                                   ou si elle a déjà un paiement
     */
    public function startCheckout(Booking $booking): string
    {
        if (BookingStatus::Pending !== $booking->getStatus()) {
            throw new \InvalidArgumentException('Seule une réservation en attente peut être payée.');
        }

        if (null !== $this->payments->findOneByBooking($booking)) {
            throw new \InvalidArgumentException('Cette réservation a déjà un paiement.');
        }

        // On enregistre d'abord le paiement « en attente » : il existe donc en base
        // même si le client ferme la page avant d'avoir payé.
        $payment = (new Payment())
            ->setBooking($booking)
            ->setAmount($booking->getTotalPrice())
            ->setCurrency($booking->getCurrency());
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        $session = $this->gateway->createCheckoutSession(
            label: $this->label($booking),
            amountCents: $this->toCents($booking->getTotalPrice()),
            currency: $booking->getCurrency(),
            reference: (string) $booking->getId(),
            successUrl: $this->urlGenerator->generate('payment_success', [], UrlGeneratorInterface::ABSOLUTE_URL)
                .'?session_id={CHECKOUT_SESSION_ID}',
            cancelUrl: $this->urlGenerator->generate('payment_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL),
        );

        // On mémorise l'identifiant de session : le webhook s'en servira pour
        // retrouver ce paiement précis et le confirmer.
        $payment->setReference($session->id);
        $this->entityManager->flush();

        return $session->url;
    }

    private function label(Booking $booking): string
    {
        return $booking->getService()?->getTitle() ?? 'Réservation';
    }

    /**
     * Convertit un montant décimal (« 120.00 ») en centimes (12000) sans passer par
     * un float, pour éviter toute erreur d'arrondi sur de l'argent.
     */
    private function toCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
        $fraction = substr($fraction.'00', 0, 2);

        return (int) $whole * 100 + (int) $fraction;
    }
}
