<?php

declare(strict_types=1);

namespace App\Payment\Service;

use App\Notification\Enum\NotificationCategory;
use App\Notification\Service\NotificationService;
use App\Payment\Entity\Subscription;
use App\Payment\Entity\SubscriptionPlan;
use App\Payment\Enum\SubscriptionStatus;
use App\Payment\Repository\SubscriptionRepository;
use App\Payment\Stripe\SubscriptionCheckoutResult;
use App\Payment\Stripe\SubscriptionGateway;
use App\Provider\Entity\ProviderProfile;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Logique métier des abonnements professionnels (§17 du CDC) : souscription,
 * changement d'offre, résiliation, synchronisation depuis les webhooks Stripe.
 */
final class SubscriptionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SubscriptionRepository $subscriptions,
        private readonly SubscriptionGateway $gateway,
        private readonly NotificationService $notifications,
    ) {
    }

    public function currentFor(ProviderProfile $provider): ?Subscription
    {
        return $this->subscriptions->findCurrentFor($provider);
    }

    /**
     * @throws \InvalidArgumentException si le prestataire a déjà un abonnement en cours
     */
    public function startCheckout(ProviderProfile $provider, SubscriptionPlan $plan, string $successUrl, string $cancelUrl): SubscriptionCheckoutResult
    {
        if (null !== $this->currentFor($provider)) {
            throw new \InvalidArgumentException('Vous avez déjà un abonnement en cours. Changez d\'offre plutôt que d\'en souscrire un second.');
        }

        $result = $this->gateway->startCheckout($provider, $plan, $successUrl, $cancelUrl);

        $subscription = (new Subscription())
            ->setProvider($provider)
            ->setPlan($plan)
            ->setStripeCustomerId($result->stripeCustomerId)
            ->setStripeSubscriptionId($result->stripeSubscriptionId)
            ->setStatus($result->alreadyActive ? SubscriptionStatus::Active : SubscriptionStatus::Incomplete);

        if ($result->alreadyActive) {
            $this->stampCurrentPeriod($subscription, $plan);
        }

        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        return $result;
    }

    /**
     * @throws \InvalidArgumentException si l'auteur n'est pas le titulaire de l'abonnement
     */
    public function cancel(Subscription $subscription, ProviderProfile $provider, bool $atPeriodEnd = true): void
    {
        if ($subscription->getProvider() !== $provider) {
            throw new \InvalidArgumentException('Seul le titulaire de l\'abonnement peut le résilier.');
        }

        if (null !== $subscription->getStripeSubscriptionId()) {
            $this->gateway->cancelSubscription($subscription->getStripeSubscriptionId(), $atPeriodEnd);
        }

        if ($atPeriodEnd) {
            $subscription->setCancelAtPeriodEnd(true);
        } else {
            $subscription->setStatus(SubscriptionStatus::Cancelled);
        }

        $this->entityManager->flush();

        $owner = $provider->getUser();
        if (null !== $owner) {
            $this->notifications->notify(
                $owner,
                NotificationCategory::Payment,
                'Abonnement résilié',
                $atPeriodEnd
                    ? 'Votre abonnement sera résilié à la fin de la période en cours.'
                    : 'Votre abonnement a été résilié.',
            );
        }
    }

    /**
     * Synchronise l'abonnement depuis un événement webhook Stripe
     * (`customer.subscription.updated` / `.deleted`) — c'est ICI, et
     * seulement ici, qu'un abonnement Stripe réel change d'état côté
     * plateforme, même principe que PaymentService::confirmBySessionReference()
     * pour le paiement à la prestation.
     */
    public function syncFromStripeSubscription(
        string $stripeSubscriptionId,
        SubscriptionStatus $status,
        ?\DateTimeImmutable $currentPeriodStart,
        ?\DateTimeImmutable $currentPeriodEnd,
    ): void {
        $subscription = $this->subscriptions->findOneByStripeSubscriptionId($stripeSubscriptionId);

        if (null === $subscription) {
            return;
        }

        $previousStatus = $subscription->getStatus();
        $subscription->setStatus($status);

        if (null !== $currentPeriodStart) {
            $subscription->setCurrentPeriodStart($currentPeriodStart);
        }
        if (null !== $currentPeriodEnd) {
            $subscription->setCurrentPeriodEnd($currentPeriodEnd);
        }

        $this->entityManager->flush();

        if ($previousStatus === $status) {
            return;
        }

        $owner = $subscription->getProvider()?->getUser();
        if (null === $owner) {
            return;
        }

        $message = match ($status) {
            SubscriptionStatus::Active => ['Abonnement activé', 'Votre abonnement est actif.'],
            SubscriptionStatus::PastDue => ['Échec de paiement', 'Le paiement de votre abonnement a échoué. Merci de mettre à jour votre moyen de paiement.'],
            SubscriptionStatus::Cancelled => ['Abonnement résilié', 'Votre abonnement a été résilié.'],
            SubscriptionStatus::Incomplete => null,
        };

        if (null !== $message) {
            $this->notifications->notify($owner, NotificationCategory::Payment, $message[0], $message[1]);
        }
    }

    private function stampCurrentPeriod(Subscription $subscription, SubscriptionPlan $plan): void
    {
        $start = new \DateTimeImmutable();
        $interval = match ($plan->getBillingPeriod()->value) {
            'yearly' => '+1 year',
            default => '+1 month',
        };

        $subscription->setCurrentPeriodStart($start);
        $subscription->setCurrentPeriodEnd($start->modify($interval));
    }
}
