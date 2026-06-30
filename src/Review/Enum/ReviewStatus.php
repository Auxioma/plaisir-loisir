<?php

declare(strict_types=1);

namespace App\Review\Enum;

/**
 * Statut de modération d'un avis.
 */
enum ReviewStatus: string
{
    case Published = 'published';
    case Pending = 'pending';
    case Rejected = 'rejected';
}
