<?php

declare(strict_types=1);

namespace App\Payment\Controller;

use App\Payment\Service\PaymentService;
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
        private readonly LoggerInterface $logger,
        private readonly string $stripeWebhookSecret,
    ) {
    }

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
                $this->paymentService->confirmBySessionReference($reference);
            } catch (\InvalidArgumentException $e) {
                // Paiement introuvable ou déjà traité : on journalise et on répond
                // tout de même 200 pour que Stripe cesse de réémettre l'événement.
                $this->logger->warning('Webhook Stripe ignoré : {message}', ['message' => $e->getMessage()]);
            }
        }

        return new Response('OK', Response::HTTP_OK);
    }
}
