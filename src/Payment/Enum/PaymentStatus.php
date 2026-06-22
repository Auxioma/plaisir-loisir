<?php

declare(strict_types=1);

namespace App\Payment\Enum;

/**
 * Statut d'un paiement.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
}
