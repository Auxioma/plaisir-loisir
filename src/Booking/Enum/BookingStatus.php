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

    /** Libellé des pastilles (espace pro, 02/10). */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Confirmed => 'Confirmée',
            self::InProgress => 'En cours',
            self::Completed => 'Terminée',
            self::Cancelled => 'Annulée',
            self::Refunded => 'Remboursée',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'blue',
            self::Confirmed => 'green',
            self::InProgress => 'orange',
            self::Completed => 'violet',
            self::Cancelled => 'red',
            self::Refunded => 'grey',
        };
    }
}
