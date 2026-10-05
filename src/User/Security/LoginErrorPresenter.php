<?php

declare(strict_types=1);

namespace App\User\Security;

use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

/**
 * Message affiché après un échec de connexion (05/10).
 *
 * Trois cas distincts plutôt qu'un « Identifiants invalides » unique, qui
 * laissait croire à une erreur de saisie quand le compte n'existait pas :
 * adresse inconnue (proposer l'inscription), mot de passe incorrect
 * (proposer « mot de passe oublié »), trop d'essais (attendre).
 */
final class LoginErrorPresenter
{
    /**
     * @return array{kind: string, message: string, params: array<string, string>}|null
     */
    public static function describe(?AuthenticationException $error): ?array
    {
        return match (true) {
            null === $error => null,
            $error instanceof UserNotFoundException => ['kind' => 'unknown', 'message' => 'Aucun compte n’est associé à cette adresse e-mail.', 'params' => []],
            // UserNotFound est parfois enveloppée dans BadCredentials.
            $error instanceof BadCredentialsException && $error->getPrevious() instanceof UserNotFoundException => ['kind' => 'unknown', 'message' => 'Aucun compte n’est associé à cette adresse e-mail.', 'params' => []],
            $error instanceof BadCredentialsException => ['kind' => 'password', 'message' => 'Mot de passe incorrect.', 'params' => []],
            $error instanceof TooManyLoginAttemptsAuthenticationException => ['kind' => 'throttled', 'message' => 'Trop de tentatives de connexion. Réessayez dans quelques minutes ou réinitialisez votre mot de passe.', 'params' => []],
            default => ['kind' => 'other', 'message' => $error->getMessageKey(), 'params' => array_map('strval', $error->getMessageData())],
        };
    }
}
