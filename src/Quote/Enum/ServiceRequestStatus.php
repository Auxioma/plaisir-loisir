<?php

declare(strict_types=1);

namespace App\Quote\Enum;

/**
 * Statut d'une demande de devis.
 */
enum ServiceRequestStatus: string
{
    case Open = 'open';      // ouverte aux propositions
    case Closed = 'closed';  // clôturée (un devis a été accepté)
}
