<?php

declare(strict_types=1);

namespace App\Booking\Enum;

/**
 * Cycle de vie d'une réservation (piloté par le workflow "booking").
 */
enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
}
