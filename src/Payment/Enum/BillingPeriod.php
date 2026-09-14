<?php

declare(strict_types=1);

namespace App\Payment\Enum;

/**
 * Périodicité de facturation d'un abonnement professionnel (§17.1 du CDC).
 */
enum BillingPeriod: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';
}
