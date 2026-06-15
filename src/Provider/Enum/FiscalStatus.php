<?php

declare(strict_types=1);

namespace App\Provider\Enum;

/**
 * Statut fiscal déclaré par un prestataire (pour la facturation / conformité).
 */
enum FiscalStatus: string
{
    case AutoEntrepreneur = 'auto_entrepreneur';
    case Individual = 'individual';        // Particulier
    case Professional = 'professional';    // Professionnel
}
