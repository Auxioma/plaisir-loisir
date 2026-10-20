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

    public function createAccount(string $email, string $providerId): string
    {
        $account = $this->stripe->accounts->create([
            'type' => 'standard',
            'country' => 'FR',
            'email' => $email,
            'metadata' => ['provider_id' => $providerId],
        ]);

        return (string) $account->id;
    }

    public function onboardingUrl(string $accountId, string $refreshUrl, string $returnUrl): string
    {
        $link = $this->stripe->accountLinks->create([
            'account' => $accountId,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);

        return (string) $link->url;
    }

    public function chargesEnabled(string $accountId): bool
    {
        return (bool) $this->stripe->accounts->retrieve($accountId)->charges_enabled;
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
