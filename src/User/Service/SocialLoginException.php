<?php

declare(strict_types=1);

namespace App\User\Service;

/**
 * Liaison impossible entre une identité externe et un compte.
 *
 * Contrairement à OAuthException, qui signale une panne technique, celle-ci
 * porte un message DESTINÉ À L'UTILISATEUR : il explique pourquoi la connexion
 * est refusée et ce qu'il peut faire. Le contrôleur l'affiche tel quel.
 */
final class SocialLoginException extends \RuntimeException
{
}
