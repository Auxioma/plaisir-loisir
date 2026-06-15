<?php

declare(strict_types=1);

namespace App\Catalog\Enum;

/**
 * Unité de tarification d'une formule.
 */
enum PricingUnit: string
{
    case PerPerson = 'per_person';
    case PerGroup = 'per_group';
    case FlatRate = 'flat_rate';
}
