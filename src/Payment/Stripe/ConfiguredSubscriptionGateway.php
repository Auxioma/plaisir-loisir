<?php

declare(strict_types=1);

namespace App\Payment\Stripe;

use App\Payment\Entity\SubscriptionPlan;
use App\Provider\Entity\ProviderProfile;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

/**
 * Abonnements professionnels, seuls paiements encaissés sur le compte de
 * TrouveMoi (17/10, demande du client) : avec une vraie clé Stripe
 * (`sk_test_…` / `sk_live_…`), le professionnel paie sur la page Stripe
 * Checkout de TrouveMoi (carte bancaire, et PayPal ou tout autre moyen activé
 * dans le tableau de bord Stripe) ; sans clé (développement, démo, tests),
 * l'abonnement est simulé et actif aussitôt.
 */
final class ConfiguredSubscriptionGateway implements SubscriptionGateway
{
    /**
     * @param \Closure(): StripeSubscriptionGateway $stripe construit seulement si une
     *                                                      clé Stripe est configurée
     *                                                      (le client Stripe refuse une clé vide)
     */
    public function __construct(
        #[AutowireServiceClosure(StripeSubscriptionGateway::class)]
        private readonly \Closure $stripe,
        private readonly MockSubscriptionGateway $mock,
        #[Autowire('%env(STRIPE_SECRET_KEY)%')]
        private readonly string $stripeSecretKey,
    ) {
    }

    public function usesStripe(): bool
    {
        return str_starts_with($this->stripeSecretKey, 'sk_');
    }

    public function startCheckout(ProviderProfile $provider, SubscriptionPlan $plan, string $successUrl, string $cancelUrl): SubscriptionCheckoutResult
    {
        return $this->gateway()->startCheckout($provider, $plan, $successUrl, $cancelUrl);
    }

    public function cancelSubscription(string $stripeSubscriptionId, bool $atPeriodEnd): void
    {
        // Un abonnement simulé (« mock_sub_… ») n'existe pas chez Stripe.
        if (str_starts_with($stripeSubscriptionId, 'mock_')) {
            return;
        }

        $this->gateway()->cancelSubscription($stripeSubscriptionId, $atPeriodEnd);
    }

    private function gateway(): SubscriptionGateway
    {
        return $this->usesStripe() ? ($this->stripe)() : $this->mock;
    }
}
