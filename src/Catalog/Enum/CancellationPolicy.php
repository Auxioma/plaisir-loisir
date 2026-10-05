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

    public function label(): string
    {
        return match ($this) {
            self::Flexible => 'Flexible',
            self::Moderate => 'Modérée',
            self::Strict => 'Stricte',
        };
    }

    /** Délai d'annulation gratuite, tel qu'affiché sur la fiche (05/10). */
    public function deadline(): string
    {
        return match ($this) {
            self::Flexible => "Jusqu'à 24 h avant",
            self::Moderate => "Jusqu'à 7 jours avant",
            self::Strict => "Jusqu'à 30 jours avant",
        };
    }
}
