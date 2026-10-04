<?php

declare(strict_types=1);

namespace App\Support\Enum;

enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Ouvert',
            self::InProgress => 'En cours',
            self::Resolved => 'Résolu',
            self::Closed => 'Fermé',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'blue',
            self::InProgress => 'orange',
            self::Resolved => 'green',
            self::Closed => 'grey',
        };
    }
}
