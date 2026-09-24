<?php

declare(strict_types=1);

namespace App\Admin\Enum;

/**
 * Motif d'un signalement (§16.4 du CDC).
 */
enum ReportReason: string
{
    case Scam = 'scam';
    case Spam = 'spam';
    case DisguisedCommercialActivity = 'disguised_commercial_activity';
    case OffensiveContent = 'offensive_content';
    case DangerousActivity = 'dangerous_activity';
    case Other = 'other';
}
