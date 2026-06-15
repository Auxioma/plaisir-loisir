<?php

declare(strict_types=1);

namespace App\Catalog\Enum;

/**
 * Période d'ouverture (saisonnalité) d'une activité.
 */
enum OpeningPeriod: string
{
    case AllYear = 'all_year';            // Toute l'année
    case SpringSummer = 'spring_summer';  // Printemps - Été
    case AutumnWinter = 'autumn_winter';  // Automne - Hiver
}
