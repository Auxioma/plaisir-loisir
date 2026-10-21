<?php

declare(strict_types=1);

namespace App\Payment\PayPal;

use App\Payment\Stripe\CheckoutSessionResult;
use App\Payment\Stripe\CheckoutState;

/**
 * Simulation de PayPal (développement, démo, tests ; jamais en production,
 * voir ConfiguredPayPalGateway) : la commande est approuvée aussitôt.
 */
final class MockPayPalGateway implements PayPalGateway
{
    public function isAvailable(): bool
    {
        return true;
    }

    public function createOrder(string $payeeEmail, string $label, string $amount, string $currency, string $reference, string $returnUrl, string $cancelUrl): CheckoutSessionResult
    {
        $id = 'MOCK'.(int) round((float) $amount * 100).'X'.strtoupper(bin2hex(random_bytes(6)));

        return new CheckoutSessionResult($id, $returnUrl.(str_contains($returnUrl, '?') ? '&' : '?').'token='.$id);
    }

    public function capture(string $orderId, ?string $merchantId = null): CheckoutState
    {
        if (!preg_match('/^MOCK(\d+)X/', $orderId, $m)) {
            return new CheckoutState(false);
        }

        return new CheckoutState(true, 'MOCKCAPTURE'.substr($orderId, -12), amountCents: (int) $m[1]);
    }
}
