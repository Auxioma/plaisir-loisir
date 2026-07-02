<?php

declare(strict_types=1);

namespace App\Payment\Stripe;

/**
 * Abstraction de la création d'une session de paiement hébergée.
 *
 * On code contre cette interface (et non directement contre le SDK Stripe) pour
 * deux raisons : garder la logique métier indépendante du prestataire, et pouvoir
 * tester les services sans appeler le vrai Stripe (on injecte un stub).
 */
interface CheckoutGateway
{
    /**
     * Crée une session de paiement et renvoie son identifiant + son URL.
     *
     * @param string $label       libellé affiché au client (ex. le titre de l'activité)
     * @param int    $amountCents montant à débiter, en centimes (Stripe raisonne en centimes)
     * @param string $currency    code ISO à 3 lettres (ex. « EUR »)
     * @param string $reference   référence interne (l'identifiant de la réservation)
     * @param string $successUrl  URL de retour en cas de succès
     * @param string $cancelUrl   URL de retour en cas d'abandon
     */
    public function createCheckoutSession(
        string $label,
        int $amountCents,
        string $currency,
        string $reference,
        string $successUrl,
        string $cancelUrl,
    ): CheckoutSessionResult;
}
