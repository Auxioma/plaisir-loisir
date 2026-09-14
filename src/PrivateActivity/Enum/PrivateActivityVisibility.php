<?php

declare(strict_types=1);

namespace App\PrivateActivity\Enum;

/**
 * Niveau de visibilité d'une activité privée (§12.3 du CDC).
 */
enum PrivateActivityVisibility: string
{
    /** Visible publiquement, y compris par un visiteur non connecté. */
    case Public = 'public';

    /** Visible uniquement par les membres connectés. */
    case MembersOnly = 'members_only';

    /** Accessible uniquement à l'organisateur et aux personnes invitées/participantes. */
    case Private = 'private';
}
