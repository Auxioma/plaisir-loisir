<?php

declare(strict_types=1);

namespace App\Payment\Stripe;

use Stripe\StripeClient;

/**
 * Implémentation réelle de {@see ConnectGateway} (SDK officiel Stripe).
 *
 * Toutes les opérations de paiement portent l'option `stripe_account` : elles
 * s'exécutent sur le compte du professionnel, jamais sur celui de TrouveMoi.
 */
final class StripeConnectGateway implements ConnectGateway
{
    public function __construct(
        private readonly StripeClient $stripe,
    ) {
    }

    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * Compte connecté créé par l'API Accounts v2 (20/10) : Stripe refuse
     * désormais `POST /v1/accounts` aux nouvelles plateformes Connect.
     * Tableau de bord complet, frais et litiges portés par Stripe =
     * l'équivalent d'un compte Standard ; configuration « merchant » pour
     * encaisser les cartes sur le compte du professionnel.
     */
    public function createAccount(string $email, string $providerId): string
    {
        $account = $this->stripe->v2->core->accounts->create([
            'contact_email' => $email,
            'identity' => ['country' => 'FR'],
            'dashboard' => 'full',
            'defaults' => [
                'currency' => 'eur',
                'locales' => ['fr-FR'],
                'responsibilities' => ['fees_collector' => 'stripe', 'losses_collector' => 'stripe'],
            ],
            'configuration' => [
                'merchant' => ['capabilities' => ['card_payments' => ['requested' => true]]],
            ],
            'metadata' => ['provider_id' => $providerId],
        ]);

        return (string) $account->id;
    }

    public function onboardingUrl(string $accountId, string $refreshUrl, string $returnUrl): string
    {
        $link = $this->stripe->v2->core->accountLinks->create([
            'account' => $accountId,
            'use_case' => [
                'type' => 'account_onboarding',
                'account_onboarding' => [
                    'configurations' => ['merchant'],
                    'refresh_url' => $refreshUrl,
                    'return_url' => $returnUrl,
                ],
            ],
        ]);

        return (string) $link->url;
    }

    /** Paiement par carte actif sur le compte (configuration « merchant »). */
    public function chargesEnabled(string $accountId): bool
    {
        $account = $this->stripe->v2->core->accounts->retrieve($accountId, ['include' => ['configuration.merchant']]);

        return 'active' === ($account->toArray()['configuration']['merchant']['capabilities']['card_payments']['status'] ?? null);
    }

    public function createCheckout(
        string $accountId,
        string $label,
        int $amountCents,
        string $currency,
        string $customerEmail,
        string $reference,
        string $successUrl,
        string $cancelUrl,
    ): CheckoutSessionResult {
        $session = $this->stripe->checkout->sessions->create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'customer_email' => $customerEmail,
            'client_reference_id' => $reference,
            'metadata' => ['payment_id' => $reference],
            'payment_intent_data' => ['description' => $label, 'metadata' => ['payment_id' => $reference]],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($currency),
                    'unit_amount' => $amountCents,
                    'product_data' => ['name' => $label],
                ],
            ]],
            'locale' => 'auto',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ], ['stripe_account' => $accountId]);

        return new CheckoutSessionResult((string) $session->id, (string) $session->url);
    }

    public function checkoutState(string $accountId, string $sessionId): CheckoutState
    {
        $session = $this->stripe->checkout->sessions->retrieve(
            $sessionId,
            ['expand' => ['payment_intent.latest_charge']],
            ['stripe_account' => $accountId],
        );

        if ('paid' !== $session->payment_status) {
            return new CheckoutState(false);
        }

        $intent = $session->payment_intent;
        $card = \is_object($intent) && \is_object($intent->latest_charge ?? null)
            ? ($intent->latest_charge->payment_method_details->card ?? null)
            : null;

        return new CheckoutState(
            paid: true,
            transactionId: \is_object($intent) ? (string) $intent->id : (string) $intent,
            cardBrand: null !== $card ? (string) $card->brand : null,
            cardLast4: null !== $card ? (string) $card->last4 : null,
            amountCents: (int) $session->amount_total,
        );
    }

    public function expireCheckout(string $accountId, string $sessionId): void
    {
        $this->stripe->checkout->sessions->expire($sessionId, [], ['stripe_account' => $accountId]);
    }
}
