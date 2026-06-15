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
}
