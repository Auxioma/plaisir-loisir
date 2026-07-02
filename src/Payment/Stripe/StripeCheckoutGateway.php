<?php

declare(strict_types=1);

namespace App\Payment\Stripe;

use Stripe\StripeClient;

/**
 * Implémentation réelle de {@see CheckoutGateway} au moyen du SDK officiel Stripe.
 *
 * C'est le seul endroit du code qui « parle » directement à Stripe : tout le reste
 * de l'application dépend de l'interface, jamais de cette classe.
 */
final class StripeCheckoutGateway implements CheckoutGateway
{
    public function __construct(
        private readonly StripeClient $stripe,
    ) {
    }

    public function createCheckoutSession(
        string $label,
        int $amountCents,
        string $currency,
        string $reference,
        string $successUrl,
        string $cancelUrl,
    ): CheckoutSessionResult {
        $session = $this->stripe->checkout->sessions->create([
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            // Permet de relier la session à notre réservation côté Stripe.
            'client_reference_id' => $reference,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($currency),
                    'unit_amount' => $amountCents,
                    'product_data' => ['name' => $label],
                ],
            ]],
        ]);

        return new CheckoutSessionResult((string) $session->id, (string) $session->url);
    }
}
