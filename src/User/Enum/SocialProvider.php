<?php

declare(strict_types=1);

namespace App\User\Enum;

/**
 * Fournisseurs d'identité acceptés par la plateforme.
 *
 * Ce sont les trois boutons de la maquette d'authentification. Attention au
 * nom : Apple appelle son service « Se connecter avec Apple » (Sign in with
 * Apple), et non « iCloud ».
 */
enum SocialProvider: string
{
    case Google = 'google';
    case Facebook = 'facebook';
    case Apple = 'apple';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::Facebook => 'Facebook',
            self::Apple => 'Apple',
        };
    }
}
