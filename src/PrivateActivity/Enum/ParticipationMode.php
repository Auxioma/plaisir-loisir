<?php

declare(strict_types=1);

namespace App\PrivateActivity\Enum;

/**
 * Mode d'inscription à une activité privée (§13.1 du CDC).
 */
enum ParticipationMode: string
{
    /** La place est attribuée immédiatement si la capacité le permet. */
    case Automatic = 'automatic';

    /** L'organisateur accepte ou refuse chaque demande. */
    case Validation = 'validation';
}
