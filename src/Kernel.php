<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Fuseau de la plateforme (France) : sans lui, PHP tourne en UTC (php.ini
     * du serveur) alors que PostgreSQL renvoie l'heure de Paris — les créneaux
     * de réservation s'affichaient décalés d'une heure (constaté le 04/10).
     */
    public const TIMEZONE = 'Europe/Paris';

    public function boot(): void
    {
        date_default_timezone_set(self::TIMEZONE);

        parent::boot();
    }
}
