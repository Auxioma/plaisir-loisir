<?php

declare(strict_types=1);

namespace App\Provider\Enum;

/**
 * Cycle de vie d'un profil prestataire (processus de vérification).
 */
enum ProviderStatus: string
{
    case Draft = 'draft';
    case PendingVerification = 'pending_verification';
    case Verified = 'verified';
    case Suspended = 'suspended';
}
