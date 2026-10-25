<?php

declare(strict_types=1);

namespace App\User\OAuth;

/** Distingue les identifiants renseignés des valeurs de démonstration. */
final class OAuthCredentials
{
    /** Préfixe qui marque une valeur de remplacement. */
    public const PLACEHOLDER_PREFIX = 'test-';

    public static function isPlaceholder(string $value): bool
    {
        return '' === trim($value) || str_starts_with($value, self::PLACEHOLDER_PREFIX);
    }

    /**
     * Toutes les valeurs fournies sont-elles de vrais identifiants ?
     */
    public static function areReal(string ...$values): bool
    {
        foreach ($values as $value) {
            if (self::isPlaceholder($value)) {
                return false;
            }
        }

        return true;
    }
}
