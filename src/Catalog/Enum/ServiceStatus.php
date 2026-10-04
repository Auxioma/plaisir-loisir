<?php

declare(strict_types=1);

namespace App\Catalog\Enum;

/**
 * Cycle de publication d'une prestation.
 *
 * Pending et Suspended (02/10, espace pro « Mes activités ») : une activité
 * soumise par le professionnel attend la validation de l'équipe (back-office)
 * avant d'être publiée ; l'équipe peut suspendre une activité publiée.
 */
enum ServiceStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Published = 'published';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Pending => 'En attente',
            self::Published => 'Publiée',
            self::Suspended => 'Suspendue',
            self::Archived => 'Archivée',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'orange',
            self::Pending => 'blue',
            self::Published => 'green',
            self::Suspended => 'red',
            self::Archived => 'grey',
        };
    }
}
