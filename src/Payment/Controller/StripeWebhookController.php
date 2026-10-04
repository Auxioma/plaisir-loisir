<?php

declare(strict_types=1);

namespace App\Payment\Controller;

use App\Catalog\Service\GiftCardService;
use App\Payment\Enum\SubscriptionStatus;
use App\Payment\Service\PaymentService;
use App\Payment\Service\SubscriptionService;
use Psr\Log\LoggerInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Point d'entrée des notifications serveur-à-serveur de Stripe (webhook).
 *
 * C'est ICI, et seulement ici, qu'un paiement est confirmé : on vérifie d'abord
 * que l'appel est bien signé par Stripe (sinon n'importe qui pourrait se faire
 * passer pour Stripe et valider des réservations non payées).
 */
final class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly GiftCardService $giftCards,
        private readonly SubscriptionService $subscriptionService,
        private readonly LoggerInterface $logger,
        private readonly string $stripeWebhookSecret,
    ) {
    }

    // URL appelee par Stripe, enregistree telle quelle dans son tableau de
    // bord : la localiser casserait les notifications de paiement.
    #[Route('/webhook/stripe', name: 'stripe_webhook', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->headers->get('Stripe-Signature', '');

        try {
            // Vérifie la signature : lève une exception si le corps a été altéré
            // ou si l'appel ne vient pas de Stripe.
            $event = Webhook::constructEvent($payload, $signature, $this->stripeWebhookSecret);
        } catch (\UnexpectedValueException) {
            return new Response('Corps de requête invalide.', Response::HTTP_BAD_REQUEST);
        } catch (SignatureVerificationException) {
            return new Response('Signature invalide.', Response::HTTP_BAD_REQUEST);
        }

        if ('checkout.session.completed' === $event->type) {
            $session = $event->data->object;
            $reference = (string) ($session->id ?? '');

            try {
                // Bon cadeau (04/10) : sa session ne correspond à aucun Payment.
                if (!$this->giftCards->confirmBySessionReference($reference)) {
                    $this->paymentService->confirmBySessionReference($reference);
                }
            } catch (\InvalidArgumentException $e) {
                // Paiement introuvable ou déjà traité : on journalise et on répond
                // tout de même 200 pour que Stripe cesse de réémettre l'événement.
                $this->logger->warning('Webhook Stripe ignoré : {message}', ['message' => $e->getMessage()]);
            }
        }

        // Abonnements professionnels (§17.2 du CDC) : ces trois événements
        // suffisent à tenir Subscription à jour — création (déjà couverte
        // par checkout.session.completed côté paiement à la prestation, mais
        // une souscription réelle en mode « subscription » déclenche aussi
        // ceux-ci), changement d'état (renouvellement, échec de paiement →
        // past_due) et résiliation.
        if (\in_array($event->type, ['customer.subscription.updated', 'customer.subscription.deleted'], true)) {
            $subscription = $event->data->object;

            $this->subscriptionService->syncFromStripeSubscription(
                stripeSubscriptionId: (string) ($subscription->id ?? ''),
                status: $this->mapStripeStatus((string) ($subscription->status ?? ''), 'customer.subscription.deleted' === $event->type),
                currentPeriodStart: $this->toDateTime($subscription->current_period_start ?? null),
                currentPeriodEnd: $this->toDateTime($subscription->current_period_end ?? null),
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
