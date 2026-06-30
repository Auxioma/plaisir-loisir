<?php

declare(strict_types=1);

namespace App\PrivateActivity\Enum;

/**
 * Réponse d'un invité à une activité privée.
 */
enum InvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
}
