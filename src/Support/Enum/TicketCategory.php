<?php

declare(strict_types=1);

namespace App\Support\Enum;

enum TicketCategory: string
{
    case Payments = 'payments';
    case Bookings = 'bookings';
    case Billing = 'billing';
    case Profile = 'profile';
    case Activities = 'activities';
    case Callback = 'callback';
    case Chat = 'chat';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Payments => 'Paiements',
            self::Bookings => 'Réservations',
            self::Billing => 'Compte & Facturation',
            self::Profile => 'Compte & Profil',
            self::Activities => 'Activités',
            self::Callback => 'Demande de rappel',
            self::Chat => 'Chat en direct',
            self::Other => 'Autre',
        };
    }
}
