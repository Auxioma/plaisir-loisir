<?php

declare(strict_types=1);

namespace App\Payment\Stripe;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

/**
 * Stripe Connect réel dès qu'une clé `sk_…` est configurée ; sinon simulé
 * en développement et en test, et INDISPONIBLE en production : un paiement
 * simulé y confirmerait une réservation que personne n'a payée.
 */
final class ConfiguredConnectGateway implements ConnectGateway
{
    /**
     * @param \Closure(): StripeConnectGateway $stripe construit seulement si une clé est configurée
     */
    public function __construct(
        #[AutowireServiceClosure(StripeConnectGateway::class)]
        private readonly \Closure $stripe,
        private readonly MockConnectGateway $mock,
        #[Autowire('%env(STRIPE_SECRET_KEY)%')]
        private readonly string $stripeSecretKey,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->usesStripe() || 'prod' !== $this->environment;
    }

    public function createAccount(string $email, string $providerId): string
    {
        return $this->gateway()->createAccount($email, $providerId);
    }

    public function onboardingUrl(string $accountId, string $refreshUrl, string $returnUrl): string
    {
        return $this->for($accountId)->onboardingUrl($accountId, $refreshUrl, $returnUrl);
    }

    public function chargesEnabled(string $accountId): bool
    {
        // Un compte simulé ne vaut rien une fois les vraies clés en place.
        if ($this->usesStripe() && str_starts_with($accountId, 'mock_')) {
            return false;
        }

        return $this->for($accountId)->chargesEnabled($accountId);
    }

    public function createCheckout(string $accountId, string $label, int $amountCents, string $currency, string $customerEmail, string $reference, string $successUrl, string $cancelUrl): CheckoutSessionResult
    {
        return $this->for($accountId)->createCheckout($accountId, $label, $amountCents, $currency, $customerEmail, $reference, $successUrl, $cancelUrl);
    }

    public function checkoutState(string $accountId, string $sessionId): CheckoutState
    {
        return $this->for($accountId)->checkoutState($accountId, $sessionId);
    }

    public function expireCheckout(string $accountId, string $sessionId): void
    {
        $this->for($accountId)->expireCheckout($accountId, $sessionId);
    }

    private function usesStripe(): bool
    {
        return str_starts_with($this->stripeSecretKey, 'sk_');
    }

    private function gateway(): ConnectGateway
    {
        if ($this->usesStripe()) {
            return ($this->stripe)();
        }
        if ('prod' === $this->environment) {
            throw new \LogicException('Paiement par carte indisponible : clé Stripe non configurée.');
        }

        return $this->mock;
    }

    private function for(string $accountId): ConnectGateway
    {
        return str_starts_with($accountId, 'mock_') && 'prod' !== $this->environment ? $this->mock : $this->gateway();
    }
}
