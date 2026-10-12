<?php

declare(strict_types=1);

namespace App\User\Security;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

/**
 * Connexion refusée à un compte « en attente » (adresse jamais vérifiée).
 * Classe dédiée pour que l'écran de connexion propose d'aller directement
 * à la vérification (LoginErrorPresenter, kind « unverified »).
 */
final class EmailNotVerifiedException extends CustomUserMessageAccountStatusException
{
    public function __construct()
    {
        parent::__construct('Votre adresse e-mail n\'est pas encore vérifiée. Recevez un code de vérification pour activer votre compte.');
    }
}
