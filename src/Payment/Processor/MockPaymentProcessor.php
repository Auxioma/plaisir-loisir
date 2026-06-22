<?php

declare(strict_types=1);

namespace App\Payment\Processor;

use App\Payment\Entity\Payment;

/**
 * Implémentation de simulation : tout paiement réussit et renvoie une fausse
 * référence de transaction. Utilisée tant que Stripe n'est pas branché.
 */
final class MockPaymentProcessor implements PaymentProcessor
{
    /**
     * Le mock réussit toujours : il renvoie donc toujours une référence (type
     * resserré à `string`, ce qui reste compatible avec l'interface `?string`).
     */
    public function charge(Payment $payment): string
    {
        return 'mock_'.bin2hex(random_bytes(8));
    }
}
