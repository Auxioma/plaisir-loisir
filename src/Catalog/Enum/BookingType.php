<?php

declare(strict_types=1);

namespace App\Catalog\Enum;

/**
 * Modèle de transaction d'une prestation.
 * Au MVP, seul "service_product" est actif ; calendar et quote viendront plus tard.
 */
enum BookingType: string
{
    case ServiceProduct = 'service_product';
    case Calendar = 'calendar';
    case Quote = 'quote';
}
