<?php

declare(strict_types=1);

namespace App\Payment\PayPal;

use App\Payment\Stripe\CheckoutSessionResult;
use App\Payment\Stripe\CheckoutState;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * PayPal réel dès que PAYPAL_CLIENT_ID / PAYPAL_CLIENT_SECRET sont
 * renseignés ; sinon simulé en développement et en test, et INDISPONIBLE
 * en production (même règle que ConfiguredConnectGateway).
 */
final class ConfiguredPayPalGateway implements PayPalGateway
{
    public function __construct(
        private readonly HttpPayPalGateway $paypal,
        private readonly MockPayPalGateway $mock,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->paypal->isAvailable() || 'prod' !== $this->environment;
    }

    public function createOrder(string $payeeEmail, string $label, string $amount, string $currency, string $reference, string $returnUrl, string $cancelUrl): CheckoutSessionResult
    {
        return $this->gateway()->createOrder($payeeEmail, $label, $amount, $currency, $reference, $returnUrl, $cancelUrl);
    }

    public function capture(string $orderId): CheckoutState
    {
        return (str_starts_with($orderId, 'MOCK') && 'prod' !== $this->environment ? $this->mock : $this->gateway())->capture($orderId);
    }

    private function gateway(): PayPalGateway
    {
        if ($this->paypal->isAvailable()) {
            return $this->paypal;
        }
        if ('prod' === $this->environment) {
            throw new \LogicException('Paiement PayPal indisponible : identifiants PayPal non configurés.');
        }

        return $this->mock;
    }
}
