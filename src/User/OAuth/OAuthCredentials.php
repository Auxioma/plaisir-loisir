<?php

declare(strict_types=1);

namespace App\User\OAuth;

/**
 * Distingue de vrais identifiants d'application d'identifiants de démonstration.
 *
 * Le CTO doit encore fournir les clés Google, Facebook et Apple. En attendant,
 * .env porte des valeurs préfixées par « test- ». Tout le code est écrit et
 * fonctionnel ; seules ces quatre lignes de configuration resteront à
 * remplacer, sans toucher au code.
 *
 * Sans ce garde-fou, les boutons mèneraient à une page d'erreur du fournisseur,
 * ce qui est bien pire qu'un bouton visiblement inactif.
 */
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
