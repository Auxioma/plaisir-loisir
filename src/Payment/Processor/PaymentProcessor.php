<?php

declare(strict_types=1);

namespace App\Payment\Processor;

use App\Payment\Entity\Payment;

/**
 * Abstraction du prestataire de paiement. On code contre cette interface ;
 * l'implémentation mock (dev/tests) sera remplacée par Stripe sans toucher au
 * reste du code.
 */
interface PaymentProcessor
{
    /**
     * Tente de débiter le paiement.
     *
     * @return string|null une référence de transaction en cas de succès, null en cas d'échec
     */
    public function charge(Payment $payment): ?string;
}
