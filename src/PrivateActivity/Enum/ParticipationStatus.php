<?php

declare(strict_types=1);

namespace App\PrivateActivity\Enum;

/**
 * Statut d'une participation (§13.2 du CDC).
 *
 * ATTENDED/ABSENT (pointage après coup) ne sont pas repris : rien dans le
 * dépôt ne marque encore une activité comme terminée pour les rendre
 * pertinents (voir PrivateActivityStatus).
 */
enum ParticipationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Refused = 'refused';
    case WaitingList = 'waiting_list';
    case Cancelled = 'cancelled';
}
