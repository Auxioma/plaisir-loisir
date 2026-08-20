<?php

declare(strict_types=1);

namespace App\User\OAuth;

use App\User\Enum\SocialProvider;
use Symfony\Component\HttpFoundation\Request;

/**
 * Contrat commun aux trois fournisseurs d'identité.
 *
 * Le déroulé est le même partout — on envoie l'utilisateur chez le
 * fournisseur, il revient avec un code, on échange ce code contre son identité
 * — mais chaque service a ses particularités : Apple répond en POST, Facebook
 * n'est pas OpenID Connect, Google l'est. Cette interface les masque.
 */
interface OAuthProviderInterface
{
    public function provider(): SocialProvider;

    /**
     * Le fournisseur est-il utilisable ?
     *
     * Faux tant que les identifiants d'application sont ceux de démonstration.
     * Le bouton reste alors inactif au lieu de mener à une erreur.
     */
    public function isConfigured(): bool;

    /**
     * URL vers laquelle rediriger l'utilisateur pour qu'il s'authentifie.
     *
     * @param string $state jeton anti-rejeu, à retrouver identique au retour
     * @param string $nonce valeur unique liant le jeton d'identité à CETTE demande
     */
    public function authorizationUrl(string $state, string $nonce): string;

    /**
     * Transforme la réponse du fournisseur en identité exploitable.
     *
     * @throws OAuthException si l'échange échoue ou si la réponse est invalide
     */
    public function fetchUser(Request $request, string $nonce): SocialUser;

    /**
     * Le fournisseur renvoie-t-il l'utilisateur en POST plutôt qu'en GET ?
     *
     * Vrai pour Apple : dès qu'on demande le nom ou l'e-mail, il impose
     * « response_mode=form_post ». La route de retour doit donc accepter les
     * deux méthodes.
     */
    public function usesFormPost(): bool;
}
