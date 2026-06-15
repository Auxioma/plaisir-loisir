<?php

declare(strict_types=1);

namespace App\Catalog\Enum;

/**
 * Politique d'annulation d'une activité.
 */
enum CancellationPolicy: string
{
    case Flexible = 'flexible';
    case Moderate = 'moderate';
    case Strict = 'strict';
}
