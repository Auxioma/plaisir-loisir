<?php

declare(strict_types=1);

namespace App\Payment\Stripe;

/**
 * État d'un paiement en ligne de réservation, tel que Stripe ou PayPal le
 * rapporte : seul `paid` déclenche la confirmation de la réservation.
 */
final readonly class CheckoutState
{
    public function __construct(
        public bool $paid,
        public ?string $transactionId = null,
        public ?string $cardBrand = null,
        public ?string $cardLast4 = null,
        /** Montant réellement payé, en centimes (contrôlé avant de confirmer). */
        public ?int $amountCents = null,
    ) {
    }
}
