<?php

declare(strict_types=1);

namespace App\Payment\Stripe;

use App\Payment\Entity\SubscriptionPlan;
use App\Provider\Entity\ProviderProfile;

/**
 * Abstraction du prestataire d'abonnement (§17.2 du CDC). On code contre
 * cette interface ; elle est résolue vers {@see ConfiguredSubscriptionGateway},
 * qui choisit Stripe ou la simulation selon la clé configurée.
 */
interface SubscriptionGateway
{
    /**
     * Démarre la souscription. L'implémentation Stripe ouvre une session de
     * paiement Stripe Checkout en mode abonnement (à confirmer par webhook) ;
     * l'implémentation mock active l'abonnement immédiatement et renvoie
     * directement l'URL de succès, sans jamais joindre Stripe.
     */
    public function startCheckout(
        ProviderProfile $provider,
        SubscriptionPlan $plan,
        string $successUrl,
        string $cancelUrl,
    ): SubscriptionCheckoutResult;

    /**
     * @param bool $atPeriodEnd true : reste actif jusqu'à la fin de la période déjà payée ;
     *                          false : résiliation immédiate
     */
    public function cancelSubscription(string $stripeSubscriptionId, bool $atPeriodEnd): void;
}
