<?php

declare(strict_types=1);

namespace App\Payment\Service;

use App\Payment\Enum\SubscriptionStatus;
use App\Payment\Repository\SubscriptionPlanRepository;
use App\Payment\Repository\SubscriptionRepository;
use App\Provider\Entity\ProviderProfile;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Droit d'utiliser la plateforme côté professionnel (20/10) : sans abonnement
 * en cours, l'espace pro se limite à l'abonnement et aux réglages
 * (ProviderSubscriptionGateSubscriber), aucune activité ne peut être soumise
 * et ses activités ne sont plus réservables (BookingService).
 *
 * Le verrou ne s'applique que si au moins une offre active existe dans le
 * back-office (sans offre, personne ne pourrait s'abonner) et si
 * PROVIDER_SUBSCRIPTION_REQUIRED vaut 1 (coupé en test : les offres créées
 * par un test restent en base pour les suivants).
 *
 * Abonnement « en cours » : actif (y compris résiliation programmée, jusqu'à
 * la fin de la période payée) ou en retard de paiement (Stripe relance le
 * prélèvement ; la ligne passe à « résilié » s'il échoue définitivement).
 */
final class SubscriptionAccess
{
    private ?bool $plansExist = null;

    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly SubscriptionPlanRepository $plans,
        #[Autowire('%env(bool:PROVIDER_SUBSCRIPTION_REQUIRED)%')]
        private readonly bool $required = true,
    ) {
    }

    public function isEnforced(): bool
    {
        return $this->required && ($this->plansExist ??= [] !== $this->plans->findActive());
    }

    public function hasAccess(ProviderProfile $provider): bool
    {
        if (!$this->isEnforced()) {
            return true;
        }

        $status = $this->subscriptions->findCurrentFor($provider)?->getStatus();

        return \in_array($status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue], true);
    }
}
