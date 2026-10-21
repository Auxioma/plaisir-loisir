<?php

declare(strict_types=1);

namespace App\Payment\PayPal;

use App\Payment\Stripe\CheckoutSessionResult;
use App\Payment\Stripe\CheckoutState;

/**
 * PayPal Checkout (20/10) : la commande est créée par l'application
 * TrouveMoi, mais avec le compte PayPal du professionnel comme bénéficiaire
 * (`payee`) : le client paie le professionnel directement, rien n'arrive sur
 * le compte PayPal de TrouveMoi.
 */
interface PayPalGateway
{
    /** Le paiement PayPal peut être proposé (identifiants, ou simulation hors production). */
    public function isAvailable(): bool;

    /**
     * Crée la commande et renvoie son identifiant + l'adresse où le client
     * l'approuve. Au retour, PayPal ajoute `?token=<id de la commande>`.
     *
     * @param string $amount montant décimal (« 60.00 »)
     */
    public function createOrder(
        string $payeeEmail,
        string $label,
        string $amount,
        string $currency,
        string $reference,
        string $returnUrl,
        string $cancelUrl,
    ): CheckoutSessionResult;

    /** Encaisse la commande approuvée par le client (sans effet si c'est déjà fait). */
    public function capture(string $orderId, ?string $merchantId = null): CheckoutState;
}
