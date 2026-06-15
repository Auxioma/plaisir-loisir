<?php

declare(strict_types=1);

namespace App\Catalog\Enum;

/**
 * Cycle de publication d'une prestation.
 */
enum ServiceStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
