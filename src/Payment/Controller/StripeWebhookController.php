<?php

declare(strict_types=1);

namespace App\Payment\Controller;

use App\Catalog\Service\GiftCardService;
use App\Payment\Enum\SubscriptionStatus;
use App\Payment\Service\BookingCheckoutService;
use App\Payment\Service\StripeConnectService;
use App\Payment\Service\SubscriptionService;
use Psr\Log\LoggerInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Point d'entrée des notifications serveur-à-serveur de Stripe (webhook).
 *
 * Stripe n'encaisse pour TrouveMoi que les abonnements des professionnels et
 * les bons cadeaux. Les prestations sont payées directement sur le compte
 * Stripe du professionnel (Stripe Connect, 20/10) : Stripe en prévient ici
 * (événement porteur de `account`), sans que l'argent passe par TrouveMoi.
 * C'est ICI, et seulement
 * ici, qu'un paiement Stripe est confirmé : on vérifie d'abord que l'appel est
 * bien signé par Stripe (sinon n'importe qui pourrait se faire passer pour
 * Stripe et activer un abonnement non payé).
 */
final class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly GiftCardService $giftCards,
        private readonly SubscriptionService $subscriptionService,
        private readonly LoggerInterface $logger,
        private readonly BookingCheckoutService $checkout,
        private readonly StripeConnectService $stripeConnect,
        private readonly string $stripeWebhookSecret,
        #[Autowire('%env(STRIPE_CONNECT_WEBHOOK_SECRET)%')]
        private readonly string $stripeConnectWebhookSecret,
    ) {
    }

    // URL appelee par Stripe, enregistree telle quelle dans son tableau de
    // bord : la localiser casserait les notifications de paiement.
    #[Route('/webhook/stripe', name: 'stripe_webhook', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->headers->get('Stripe-Signature', '');

        // Deux points d'écoute Stripe peuvent viser cette URL : celui du compte
        // TrouveMoi et celui des comptes connectés des professionnels (Stripe
        // Connect, 20/10), chacun avec son secret de signature.
        $event = null;
        foreach (array_filter([$this->stripeWebhookSecret, $this->stripeConnectWebhookSecret]) as $secret) {
            try {
                // Vérifie la signature : lève une exception si le corps a été
                // altéré ou si l'appel ne vient pas de Stripe.
                $event = Webhook::constructEvent($payload, $signature, $secret);
                break;
            } catch (\UnexpectedValueException) {
                return new Response('Corps de requête invalide.', Response::HTTP_BAD_REQUEST);
            } catch (SignatureVerificationException) {
            }
        }
        if (null === $event) {
            return new Response('Signature invalide.', Response::HTTP_BAD_REQUEST);
        }

        // Événement d'un compte professionnel : paiement d'une réservation
        // (direct sur son compte) ou changement d'état de son compte.
        if (null !== ($event->account ?? null)) {
            $object = $event->data->object;
            if ('checkout.session.completed' === $event->type
                && !$this->checkout->completeFromStripe((string) ($object->id ?? ''), (string) $event->account)) {
                $this->logger->warning('Webhook Stripe Connect ignoré : session {id} inconnue.', ['id' => (string) ($object->id ?? '')]);
            }
            if ('account.updated' === $event->type) {
                $this->stripeConnect->sync((string) $event->account, (bool) ($object->charges_enabled ?? false));
            }

            return new Response('OK', Response::HTTP_OK);
        }

        if ('checkout.session.completed' === $event->type) {
            $session = $event->data->object;

            if ('subscription' === ($session->mode ?? null)) {
                // Abonnement professionnel payé : on le rattache et l'active.
                $this->subscriptionService->activateFromCheckout(
                    providerId: (string) ($session->client_reference_id ?? ''),
                    stripeCustomerId: (string) ($session->customer ?? ''),
                    stripeSubscriptionId: (string) ($session->subscription ?? ''),
                );
            } elseif (!$this->giftCards->confirmBySessionReference((string) ($session->id ?? ''))) {
                // Session inconnue : on journalise et on répond tout de même 200
                // pour que Stripe cesse de réémettre l'événement.
                $this->logger->warning('Webhook Stripe ignoré : session {id} inconnue.', ['id' => (string) ($session->id ?? '')]);
            }
        }

        // Abonnements professionnels (§17.2 du CDC) : changement d'état
        // (renouvellement, échec de paiement → past_due) et résiliation.
        if (\in_array($event->type, ['customer.subscription.updated', 'customer.subscription.deleted'], true)) {
            $subscription = $event->data->object;

            $this->subscriptionService->syncFromStripeSubscription(
                stripeSubscriptionId: (string) ($subscription->id ?? ''),
                status: $this->mapStripeStatus((string) ($subscription->status ?? ''), 'customer.subscription.deleted' === $event->type),
                // Versions récentes de l'API Stripe : la période vit sur l'article.
                currentPeriodStart: $this->toDateTime($subscription->current_period_start ?? $subscription->items->data[0]->current_period_start ?? null),
                currentPeriodEnd: $this->toDateTime($subscription->current_period_end ?? $subscription->items->data[0]->current_period_end ?? null),
            );
        }

        return new Response('OK', Response::HTTP_OK);
    }

    /**
     * États Stripe (`incomplete`, `trialing`, `active`, `past_due`,
     * `canceled`, `unpaid`, `paused`) ramenés aux quatre que la plateforme
     * distingue (SubscriptionStatus) : trialing se comporte comme actif pour
     * l'usage de la plateforme (aucune période d'essai n'est proposée
     * aujourd'hui, mais Stripe peut renvoyer cet état), unpaid/paused comme
     * un paiement en retard plutôt qu'une résiliation — Stripe continue de
     * relancer avant d'abandonner.
     */
    private function mapStripeStatus(string $stripeStatus, bool $deleted): SubscriptionStatus
    {
        if ($deleted) {
            return SubscriptionStatus::Cancelled;
        }

        return match ($stripeStatus) {
            'active', 'trialing' => SubscriptionStatus::Active,
            'canceled' => SubscriptionStatus::Cancelled,
            'past_due', 'unpaid', 'paused' => SubscriptionStatus::PastDue,
            default => SubscriptionStatus::Incomplete,
        };
    }

    private function toDateTime(int|float|null $timestamp): ?\DateTimeImmutable
    {
        return null !== $timestamp ? (new \DateTimeImmutable())->setTimestamp((int) $timestamp) : null;
    }
}
