<?php

declare(strict_types=1);

namespace App\Payment\Stripe;

/**
 * Résultat de la création d'une session de paiement hébergée (Stripe Checkout).
 *
 * On n'expose au reste de l'application que le strict nécessaire :
 * - l'identifiant de la session (pour retrouver le paiement au retour du webhook) ;
 * - l'URL vers laquelle rediriger le client pour qu'il paie.
 */
final readonly class CheckoutSessionResult
{
    public function __construct(
        public string $id,
        public string $url,
    ) {
    }
}
