<?php

declare(strict_types=1);

namespace App\Payment\Stripe;

/**
 * Stripe Connect (20/10) : le professionnel relie SON compte Stripe
 * (compte « Standard », qu'il gère lui-même sur dashboard.stripe.com) et le
 * client paie la réservation par carte directement sur ce compte (« direct
 * charge ») : l'argent ne transite jamais par le compte Stripe de TrouveMoi,
 * qui ne prélève aucune commission.
 */
interface ConnectGateway
{
    /** Le paiement par carte peut être proposé (clé Stripe, ou simulation hors production). */
    public function isAvailable(): bool;

    /** Crée le compte Stripe du professionnel et renvoie son identifiant (« acct_… »). */
    public function createAccount(string $email, string $providerId): string;

    /** Lien d'inscription Stripe (identité, IBAN…), à usage unique et de courte durée. */
    public function onboardingUrl(string $accountId, string $refreshUrl, string $returnUrl): string;

    /** Stripe autorise-t-il les paiements sur ce compte (inscription terminée et validée) ? */
    public function chargesEnabled(string $accountId): bool;

    /**
     * Session Stripe Checkout créée SUR le compte du professionnel.
     *
     * @param string $successUrl peut contenir {CHECKOUT_SESSION_ID}
     */
    public function createCheckout(
        string $accountId,
        string $label,
        int $amountCents,
        string $currency,
        string $customerEmail,
        string $reference,
        string $successUrl,
        string $cancelUrl,
    ): CheckoutSessionResult;

    /** État de la session (payée ou non, carte utilisée). */
    public function checkoutState(string $accountId, string $sessionId): CheckoutState;

    /** Clôt une session non payée (le client a choisi un autre moyen). */
    public function expireCheckout(string $accountId, string $sessionId): void;
}
