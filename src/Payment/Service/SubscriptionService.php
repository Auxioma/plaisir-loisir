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
use Psr\Log\LoggerInterface;

/**
 * Logique métier des abonnements professionnels (§17 du CDC) : souscription,
 * changement d'offre, résiliation, synchronisation depuis les webhooks Stripe.
 *
 * Ce sont les seuls paiements encaissés sur le compte de TrouveMoi (17/10) ;
 * les prestations sont réglées directement aux professionnels.
 */
final class SubscriptionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SubscriptionRepository $subscriptions,
        private readonly SubscriptionGateway $gateway,
        private readonly NotificationService $notifications,
        private readonly ?LoggerInterface $logger = null,
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
        $current = $this->currentFor($provider);
        if (SubscriptionStatus::Incomplete === $current?->getStatus()) {
            // Paiement abandonné sur la page Stripe : la tentative est close,
            // le professionnel peut en démarrer une nouvelle.
            $current->setStatus(SubscriptionStatus::Cancelled);
            $current = null;
        }
        if (null !== $current) {
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
     * Rattrapage sans webhook (20/10) : une souscription restée « en attente »
     * est vérifiée directement chez Stripe ; payée, elle est activée avec sa
     * période. Appelé au retour de Stripe et à l'ouverture de la page
     * abonnement. Une erreur Stripe laisse simplement l'état inchangé.
     */
    public function reconcile(ProviderProfile $provider): void
    {
        $current = $this->currentFor($provider);
        $customerId = $current?->getStripeCustomerId();
        if (null === $current || SubscriptionStatus::Incomplete !== $current->getStatus() || null === $customerId || '' === $customerId) {
            return;
        }

        try {
            $paid = $this->gateway->findPaidSubscription($customerId, (string) $provider->getId());
        } catch (\Throwable $e) {
            $this->logger?->error('Abonnement : vérification Stripe impossible pour {id} : {error}', ['id' => (string) $provider->getId(), 'error' => $e->getMessage()]);

            return;
        }
        if (null === $paid) {
            return;
        }

        $this->activateFromCheckout((string) $provider->getId(), $customerId, $paid['id']);
        $this->syncFromStripeSubscription(
            $paid['id'],
            'past_due' === $paid['status'] ? SubscriptionStatus::PastDue : SubscriptionStatus::Active,
            null !== $paid['start'] ? (new \DateTimeImmutable())->setTimestamp((int) $paid['start']) : null,
            null !== $paid['end'] ? (new \DateTimeImmutable())->setTimestamp((int) $paid['end']) : null,
        );
    }

    /**
     * Paiement validé sur Stripe Checkout (webhook `checkout.session.completed`
     * en mode abonnement) : rattache l'abonnement Stripe à la tentative en
     * attente du professionnel et l'active. Idempotent.
     */
    public function activateFromCheckout(string $providerId, string $stripeCustomerId, string $stripeSubscriptionId): void
    {
        if ('' === $stripeSubscriptionId) {
            return;
        }

        $subscription = $this->subscriptions->findOneByStripeSubscriptionId($stripeSubscriptionId)
            ?? $this->subscriptions->findPendingForProviderId($providerId);
        if (null === $subscription) {
            return;
        }

        $subscription->setStripeSubscriptionId($stripeSubscriptionId);
        if ('' !== $stripeCustomerId) {
            $subscription->setStripeCustomerId($stripeCustomerId);
        }
        if (SubscriptionStatus::Active === $subscription->getStatus()) {
            $this->entityManager->flush();

            return;
        }

        $subscription->setStatus(SubscriptionStatus::Active);
        if (null === $subscription->getCurrentPeriodEnd() && null !== $plan = $subscription->getPlan()) {
            $this->stampCurrentPeriod($subscription, $plan);
        }
        $this->entityManager->flush();

        $owner = $subscription->getProvider()?->getUser();
        if (null !== $owner) {
            $this->notifications->notify($owner, NotificationCategory::Payment, 'Abonnement activé', 'Votre abonnement est actif. Merci !');
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
