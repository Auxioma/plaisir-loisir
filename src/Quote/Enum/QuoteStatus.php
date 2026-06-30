<?php

declare(strict_types=1);

namespace App\Quote\Enum;

/**
 * Statut d'un devis proposé par un annonceur.
 */
enum QuoteStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
}
