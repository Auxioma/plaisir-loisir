<?php

declare(strict_types=1);

namespace App\User\OAuth;

/**
 * Échec d'un échange avec un fournisseur d'identité.
 *
 * Le message est destiné aux journaux, pas à l'écran : il peut contenir la
 * réponse brute du fournisseur. Le contrôleur affiche un texte générique.
 */
final class OAuthException extends \RuntimeException
{
}
