<?php

declare(strict_types=1);

namespace App\PrivateActivity\Enum;

/**
 * Cycle de vie d'une activité privée.
 *
 * Simplifié par rapport au workflow indicatif du CDC (§24.2 : DRAFT ->
 * PENDING_MODERATION -> PUBLISHED -> FULL -> IN_PROGRESS -> COMPLETED, avec
 * CANCELLED/EXPIRED/ARCHIVED). Aucun brouillon ni modération n'existe encore
 * (comme ServiceRequest, qui n'a pas non plus de DRAFT) : une activité est
 * publiée dès sa création. La distinction IN_PROGRESS/COMPLETED/EXPIRED,
 * liée à la date de l'activité, est laissée à une itération suivante.
 */
enum PrivateActivityStatus: string
{
    /** Ouverte : accepte des demandes de participation. */
    case Open = 'open';

    /** Capacité maximale atteinte : n'accepte plus de nouvelle place directe. */
    case Full = 'full';

    /** Annulée par l'organisateur. */
    case Cancelled = 'cancelled';
}
