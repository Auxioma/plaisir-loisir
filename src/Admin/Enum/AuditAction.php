<?php

declare(strict_types=1);

namespace App\Admin\Enum;

/**
 * Opérations sensibles que le CDC (§18.1) demande explicitement d'historiser.
 *
 * ContentDeleted et SubscriptionManualAction sont repris tels quels dans le
 * CDC mais ne sont déclenchés nulle part aujourd'hui : aucun écran du
 * back-office ne supprime de contenu, et l'écran Abonnements est
 * volontairement en lecture seule (voir SubscriptionCrudController — une
 * ligne y reflète Stripe, la modifier à la main la rendrait fausse). Les
 * quatre autres sont réellement déclenchés, voir UserCrudController et
 * ReportCrudController.
 */
enum AuditAction: string
{
    case AccountSuspended = 'account_suspended';
    case AccountAnonymized = 'account_anonymized';
    case RoleChanged = 'role_changed';
    case ContentDeleted = 'content_deleted';
    case ReportProcessed = 'report_processed';
    case SubscriptionManualAction = 'subscription_manual_action';
}
