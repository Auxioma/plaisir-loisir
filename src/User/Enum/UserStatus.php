<?php

declare(strict_types=1);

namespace App\User\Enum;

/**
 * Cycle de vie d'un compte utilisateur.
 */
enum UserStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Suspended = 'suspended';
}
