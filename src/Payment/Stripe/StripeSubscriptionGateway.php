<?php

declare(strict_types=1);

namespace App\Payment\Stripe;

use App\Payment\Entity\SubscriptionPlan;
use App\Provider\Entity\ProviderProfile;
use Stripe\StripeClient;

/**
 * Implémentation réelle de {@see SubscriptionGateway} au moyen du SDK Stripe.
 *
 * Comme {@see StripeCheckoutGateway}, c'est le seul endroit du code qui
 * « parle » directement à Stripe pour les abonnements : tout le reste dépend
 * de l'interface, jamais de cette classe.
 *
 * NON EXERCÉE EN CONDITIONS RÉELLES À CE STADE DU PROJET : `config/services.yaml`
 * résout `SubscriptionGateway` vers {@see MockSubscriptionGateway}, faute de
 * clés Stripe de test dans cet environnement (STRIPE_SECRET_KEY vide, voir
 * .env). Le code suit fidèlement l'API Stripe Checkout (mode « subscription »)
 * et Billing documentée, dans le même style que le paiement à la prestation
 * déjà écrit et testé ; il reste à vérifier avec un vrai compte Stripe de
 * test avant toute mise en production.
 */
final class StripeSubscriptionGateway implements SubscriptionGateway
{
    public function __construct(
        private readonly StripeClient $stripe,
    ) {
    }

    public function startCheckout(
        ProviderProfile $provider,
        SubscriptionPlan $plan,
        string $successUrl,
        string $cancelUrl,
    ): SubscriptionCheckoutResult {
        $user = $provider->getUser();

        // Un même compte Stripe Customer sert à tous les abonnements successifs
        // d'un même prestataire : Stripe le retrouve par e-mail plutôt que d'en
        // créer un nouveau à chaque fois, pour ne pas polluer son tableau de bord.
        $customers = $this->stripe->customers->all(['email' => $user?->getEmail(), 'limit' => 1]);
        $customer = $customers->first() ?? $this->stripe->customers->create([
            'email' => $user?->getEmail(),
            'name' => $provider->getDisplayName(),
        ]);

        $session = $this->stripe->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $customer->id,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            // Retrouve le prestataire depuis le webhook, qui ne reçoit que des
            // identifiants Stripe.
            'client_reference_id' => (string) $provider->getId(),
            'line_items' => [[
                'quantity' => 1,
                'price' => $plan->getStripePriceId(),
            ]],
        ]);

        return new SubscriptionCheckoutResult(
            stripeCustomerId: (string) $customer->id,
            redirectUrl: (string) $session->url,
            alreadyActive: false,
        );
    }

    public function cancelSubscription(string $stripeSubscriptionId, bool $atPeriodEnd): void
    {
        if ($atPeriodEnd) {
            $this->stripe->subscriptions->update($stripeSubscriptionId, ['cancel_at_period_end' => true]);

            return;
        }

        $this->stripe->subscriptions->cancel($stripeSubscriptionId);
    }
}
