<?php

declare(strict_types=1);

namespace App\Payment\Stripe;

use App\Payment\Entity\SubscriptionPlan;
use App\Provider\Entity\ProviderProfile;

/**
 * Implémentation de simulation, active par défaut (voir config/services.yaml,
 * même principe que MockPaymentProcessor) : tant que le projet ne dispose pas
 * de clés Stripe réelles, un abonnement se démarre et se résilie
 * immédiatement, sans jamais joindre Stripe.
 */
final class MockSubscriptionGateway implements SubscriptionGateway
{
    public function startCheckout(
        ProviderProfile $provider,
        SubscriptionPlan $plan,
        string $successUrl,
        string $cancelUrl,
    ): SubscriptionCheckoutResult {
        return new SubscriptionCheckoutResult(
            stripeCustomerId: 'mock_cus_'.bin2hex(random_bytes(8)),
            redirectUrl: $successUrl,
            alreadyActive: true,
            stripeSubscriptionId: 'mock_sub_'.bin2hex(random_bytes(8)),
        );
    }

    public function cancelSubscription(string $stripeSubscriptionId, bool $atPeriodEnd): void
    {
        // Rien à faire : aucun état distant à synchroniser.
    }
}
