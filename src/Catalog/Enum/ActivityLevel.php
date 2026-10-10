<?php

declare(strict_types=1);

namespace App\Catalog\Enum;

/**
 * Niveau requis pour participer à une activité.
 */
enum ActivityLevel: string
{
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';
    case AllLevels = 'all_levels';

    public function label(): string
    {
        return match ($this) {
            self::Beginner => 'Débutant',
            self::Intermediate => 'Intermédiaire',
            self::Advanced => 'Confirmé',
            self::AllLevels => 'Tous niveaux',
        };
    }
}
