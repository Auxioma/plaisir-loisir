<?php

declare(strict_types=1);

namespace App\Payment\Service;

use App\Booking\Entity\Booking;
use App\Booking\Enum\BookingStatus;
use App\Payment\Entity\Payment;
use App\Payment\PayPal\PayPalGateway;
use App\Payment\Repository\PaymentRepository;
use App\Payment\Stripe\ConnectGateway;
use App\Provider\Service\ProviderPaymentMethods;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Paiement d'une réservation (20/10, maquette « Parcours du client —
 * Paiement d'une réservation ») : le client choisit l'un des moyens acceptés
 * par le professionnel.
 *
 *  - Stripe CB : session Stripe Checkout créée sur le compte Stripe du
 *    professionnel ; confirmée au retour du client ET par le webhook Stripe
 *    Connect (si le client ferme son navigateur avant le retour).
 *  - PayPal : commande au bénéfice du compte PayPal du professionnel ;
 *    encaissée au retour du client (rien n'est débité sans ce retour).
 *  - Sur place : la demande part au professionnel (PaymentService::request).
 *
 * Aucune somme ne transite par TrouveMoi.
 */
final class BookingCheckoutService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentRepository $payments,
        private readonly PaymentService $paymentService,
        private readonly ProviderPaymentMethods $methods,
        private readonly ConnectGateway $stripe,
        private readonly PayPalGateway $paypal,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Ouvre le paiement en ligne et renvoie l'adresse Stripe / PayPal où
     * envoyer le client.
     *
     * @param string $returnUrl retour après paiement (Stripe y ajoute session_id, PayPal token)
     *
     * @throws \InvalidArgumentException réservation non payable ou moyen non proposé
     */
    public function start(Booking $booking, string $channel, string $returnUrl, string $cancelUrl): string
    {
        $provider = $booking->getService()?->getProvider();
        if (BookingStatus::Pending !== $booking->getStatus() || null === $provider) {
            throw new \InvalidArgumentException('Cette réservation n’est plus en attente de paiement.');
        }
        $offered = $this->methods->offered($provider);
        if (!isset($offered[$channel]) || !$offered[$channel]['online']) {
            throw new \InvalidArgumentException('Ce moyen de paiement n’est pas proposé par l’organisateur.');
        }

        $payment = $this->payments->findOneByBooking($booking);
        if (null !== $payment && ($payment->isPaid() || !$payment->isOnline())) {
            throw new \InvalidArgumentException($payment->isPaid() ? 'Cette réservation est déjà réglée.' : 'Votre demande a déjà été envoyée à l’organisateur.');
        }
        if (null === $payment) {
            $payment = (new Payment())->setBooking($booking);
            $this->entityManager->persist($payment);
        }
        $this->closePreviousCheckout($payment);

        $payment->setAmount($booking->getTotalPrice())->setCurrency($booking->getCurrency());
        $label = sprintf('%s — réservation #%s', $booking->getService()->getTitle(), $booking->getReference());
        $reference = (string) $booking->getId();

        try {
            if (ProviderPaymentMethods::STRIPE === $channel) {
                $account = (string) $provider->getStripeAccountId();
                $session = $this->stripe->createCheckout(
                    $account,
                    $label,
                    (int) round((float) $booking->getTotalPrice() * 100),
                    $booking->getCurrency(),
                    (string) $booking->getClient()?->getEmail(),
                    $reference,
                    $returnUrl.(str_contains($returnUrl, '?') ? '&' : '?').'session_id={CHECKOUT_SESSION_ID}',
                    $cancelUrl,
                );
                $payment->setExternalAccount($account);
            } else {
                $session = $this->paypal->createOrder(
                    (string) ($provider->getPaypalMerchantId() ?? $provider->getPaymentPaypal()),
                    $label,
                    number_format((float) $booking->getTotalPrice(), 2, '.', ''),
                    $booking->getCurrency(),
                    $reference,
                    $returnUrl,
                    $cancelUrl,
                );
                $payment->setExternalAccount($provider->getPaypalMerchantId() ?? $provider->getPaymentPaypal());
            }
        } catch (\Throwable $e) {
            $this->logger->error('Paiement {channel} impossible pour la réservation {id} : {error}', ['channel' => $channel, 'id' => $reference, 'error' => $e->getMessage()]);

            throw new \InvalidArgumentException('Le service de paiement est momentanément indisponible. Réessayez dans un instant ou choisissez un autre moyen.', 0, $e);
        }

        $payment->setChannel($channel)->setExternalId($session->id);
        $this->entityManager->flush();

        return $session->url;
    }

    /**
     * Le client choisit de régler sur place (ou l'organisateur n'a rien
     * configuré) : la demande part au professionnel.
     */
    public function payOnSite(Booking $booking): Payment
    {
        $payment = $this->payments->findOneByBooking($booking);
        if (null !== $payment && !$payment->isPaid()) {
            $this->closePreviousCheckout($payment);
        }

        return $this->paymentService->request($booking);
    }

    /**
     * Retour du client depuis Stripe / PayPal : vérifie le paiement auprès du
     * prestataire de paiement (jamais sur la seule foi de l'URL de retour).
     *
     * @param string|null $token jeton PayPal de l'URL de retour
     *
     * @return bool la réservation est réglée
     */
    public function complete(Booking $booking, ?string $token = null): bool
    {
        $payment = $this->payments->findOneByBooking($booking);
        if (null === $payment || !$payment->isOnline() || null === $payment->getExternalId()) {
            return false;
        }
        if ($payment->isPaid()) {
            return true;
        }

        try {
            if (Payment::CHANNEL_STRIPE === $payment->getChannel()) {
                $state = $this->stripe->checkoutState((string) $payment->getExternalAccount(), $payment->getExternalId());
            } else {
                // La commande approuvée doit être celle de CETTE réservation,
                // et on n'encaisse pas une réservation annulée entre-temps.
                if ($token !== $payment->getExternalId() || BookingStatus::Pending !== $booking->getStatus()) {
                    return false;
                }
                $state = $this->paypal->capture($payment->getExternalId(), $payment->getExternalAccount());
            }
        } catch (\Throwable $e) {
            $this->logger->error('Vérification du paiement {id} impossible : {error}', ['id' => (string) $payment->getId(), 'error' => $e->getMessage()]);

            return false;
        }

        return $this->paymentService->recordOnlinePayment($payment, $state);
    }

    /**
     * Webhook Stripe Connect (`checkout.session.completed` sur le compte du
     * professionnel).
     *
     * @return bool la session correspond à un paiement de réservation
     */
    public function completeFromStripe(string $sessionId, string $accountId): bool
    {
        $payment = $this->payments->findOneByExternalId($sessionId);
        if (null === $payment || Payment::CHANNEL_STRIPE !== $payment->getChannel() || $payment->getExternalAccount() !== $accountId) {
            return false;
        }

        if (!$payment->isPaid()) {
            $this->paymentService->recordOnlinePayment($payment, $this->stripe->checkoutState($accountId, $sessionId));
        }

        return true;
    }

    /**
     * Le client abandonne sa réservation alors qu'un paiement en ligne était
     * ouvert : la session Stripe est close et le règlement en cours effacé.
     */
    public function discard(Payment $payment): void
    {
        if ($payment->isPaid()) {
            return;
        }
        $this->closePreviousCheckout($payment);
        $this->entityManager->remove($payment);
    }

    /**
     * Une seule session Stripe ouverte par réservation : avant d'en ouvrir
     * une autre (ou de basculer sur place), la précédente est vérifiée — si
     * elle a été payée entre-temps, le règlement est enregistré et rien
     * d'autre n'est ouvert — puis close. Sans réponse de Stripe, on
     * s'abstient : écraser la session ferait perdre la trace d'un paiement.
     *
     * @throws \InvalidArgumentException déjà réglée, ou Stripe injoignable
     */
    private function closePreviousCheckout(Payment $payment): void
    {
        $account = $payment->getExternalAccount();
        $session = $payment->getExternalId();
        if (Payment::CHANNEL_STRIPE !== $payment->getChannel() || null === $session || null === $account) {
            return;
        }

        try {
            $state = $this->stripe->checkoutState($account, $session);
        } catch (\Throwable $e) {
            $this->logger->error('Session Stripe {id} non vérifiée : {error}', ['id' => $session, 'error' => $e->getMessage()]);

            throw new \InvalidArgumentException('Le service de paiement est momentanément indisponible. Réessayez dans un instant.', 0, $e);
        }

        if (!$state->paid) {
            try {
                $this->stripe->expireCheckout($account, $session);
            } catch (\Throwable $e) {
                // Déjà expirée (au bout de 24 h)… ou payée à l'instant : on revérifie.
                $this->logger->notice('Session Stripe {id} non close : {error}', ['id' => $session, 'error' => $e->getMessage()]);
                $state = $this->stripe->checkoutState($account, $session);
            }

            if (!$state->paid) {
                return;
            }
        }

        $this->paymentService->recordOnlinePayment($payment, $state);

        throw new \InvalidArgumentException('Cette réservation est déjà réglée.');
    }
}
