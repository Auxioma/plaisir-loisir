<?php

declare(strict_types=1);

namespace App\Payment\Stripe;

use App\Payment\Entity\SubscriptionPlan;
use App\Provider\Entity\ProviderProfile;
use Stripe\StripeClient;

/**
 * Implémentation réelle de {@see SubscriptionGateway} au moyen du SDK Stripe,
 * utilisée dès qu'une clé `sk_…` est configurée (ConfiguredSubscriptionGateway).
 *
 * Comme {@see StripeCheckoutGateway}, c'est le seul endroit du code qui
 * « parle » directement à Stripe pour les abonnements. Le paiement arrive sur
 * le compte Stripe de TrouveMoi. Aucun `payment_method_types` n'est imposé :
 * Stripe propose les moyens activés dans son tableau de bord (carte bancaire,
 * PayPal…). L'activation est confirmée par le webhook
 * (`checkout.session.completed`, puis `customer.subscription.*`).
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
            // Repris par le webhook pour rattacher l'abonnement Stripe à la bonne ligne.
            'metadata' => ['provider_id' => (string) $provider->getId(), 'plan' => $plan->getSlug()],
            'subscription_data' => ['metadata' => ['provider_id' => (string) $provider->getId(), 'plan' => $plan->getSlug()]],
            'line_items' => [[
                'quantity' => 1,
                ...$this->price($plan),
            ]],
        ]);

        return new SubscriptionCheckoutResult(
            stripeCustomerId: (string) $customer->id,
            redirectUrl: (string) $session->url,
            alreadyActive: false,
        );
    }

    /**
     * Prix Stripe de l'offre : celui créé dans le tableau de bord Stripe s'il
     * est renseigné dans le back-office, sinon un prix récurrent construit à
     * la volée depuis le tarif de l'offre.
     *
     * @return array<string, mixed>
     */
    private function price(SubscriptionPlan $plan): array
    {
        if (null !== $plan->getStripePriceId() && '' !== $plan->getStripePriceId()) {
            return ['price' => $plan->getStripePriceId()];
        }

        [$whole, $fraction] = array_pad(explode('.', $plan->getPriceAmount(), 2), 2, '0');

        return ['price_data' => [
            'currency' => strtolower($plan->getCurrency()),
            'unit_amount' => (int) $whole * 100 + (int) substr($fraction.'00', 0, 2),
            'recurring' => ['interval' => 'yearly' === $plan->getBillingPeriod()->value ? 'year' : 'month'],
            'product_data' => ['name' => 'TrouveMoi — '.$plan->getName()],
        ]];
    }

    public function findPaidSubscription(string $stripeCustomerId, string $providerId): ?array
    {
        $list = $this->stripe->subscriptions->all(['customer' => $stripeCustomerId, 'status' => 'all', 'limit' => 10]);
        foreach ($list->data as $subscription) {
            $s = $subscription->toArray();
            if (($s['metadata']['provider_id'] ?? null) !== $providerId || !\in_array($s['status'] ?? '', ['active', 'trialing', 'past_due'], true)) {
                continue;
            }

            // Versions récentes de l'API : la période vit sur l'article.
            $item = $s['items']['data'][0] ?? [];

            return [
                'id' => (string) $s['id'],
                'status' => (string) $s['status'],
                'start' => $s['current_period_start'] ?? $item['current_period_start'] ?? null,
                'end' => $s['current_period_end'] ?? $item['current_period_end'] ?? null,
            ];
        }

        return null;
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
