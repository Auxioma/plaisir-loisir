<?php

declare(strict_types=1);

namespace App\Notification\Enum;

/**
 * Catégorie d'une notification (regroupe les notifications par sujet).
 */
enum NotificationCategory: string
{
    case Booking = 'booking';        // Réservations
    case Review = 'review';          // Avis
    case Payment = 'payment';        // Paiements et abonnements
    case System = 'system';          // Système / compte
    case Messaging = 'messaging';    // Nouveau message
    case Quote = 'quote';            // Demandes et propositions (§10, §11 du CDC)
    case Activity = 'activity';      // Activités privées (§12, §13 du CDC)
}
