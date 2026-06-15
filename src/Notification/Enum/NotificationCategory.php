<?php

declare(strict_types=1);

namespace App\Notification\Enum;

/**
 * Catégorie d'une notification (regroupe les notifications par sujet).
 */
enum NotificationCategory: string
{
    case Booking = 'booking';    // Réservations
    case Review = 'review';      // Avis
    case Payment = 'payment';    // Paiements
    case System = 'system';      // Système / compte
}
