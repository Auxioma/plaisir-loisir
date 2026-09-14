<?php

declare(strict_types=1);

namespace App\Admin\Enum;

/**
 * Traitement d'un signalement par la modération (§18 du CDC : « traiter,
 * classer, commenter et historiser les décisions »).
 */
enum ReportStatus: string
{
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
}
