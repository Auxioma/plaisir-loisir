<?php

declare(strict_types=1);

namespace App\Payment\Stripe;

/**
 * Simulation de Stripe Connect (développement, démo, tests ; jamais en
 * production, voir ConfiguredConnectGateway) : le compte est relié aussitôt
 * et le paiement réussit au retour, carte « Visa 4242 ».
 */
final class MockConnectGateway implements ConnectGateway
{
    public function isAvailable(): bool
    {
        return true;
    }

    public function createAccount(string $email, string $providerId): string
    {
        return 'mock_acct_'.bin2hex(random_bytes(8));
    }

    public function onboardingUrl(string $accountId, string $refreshUrl, string $returnUrl): string
    {
        return $returnUrl;
    }

    public function chargesEnabled(string $accountId): bool
    {
        return str_starts_with($accountId, 'mock_');
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
        $id = 'mock_cs_'.$amountCents.'_'.bin2hex(random_bytes(8));

        return new CheckoutSessionResult($id, str_replace('{CHECKOUT_SESSION_ID}', $id, $successUrl));
    }

    public function checkoutState(string $accountId, string $sessionId): CheckoutState
    {
        if (!preg_match('/^mock_cs_(\d+)_/', $sessionId, $m)) {
            return new CheckoutState(false);
        }

        return new CheckoutState(true, 'mock_pi_'.substr($sessionId, -16), 'visa', '4242', (int) $m[1]);
    }

    public function expireCheckout(string $accountId, string $sessionId): void
    {
    }
}
