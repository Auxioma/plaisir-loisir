<?php

declare(strict_types=1);

namespace App\User\OAuth;

use App\User\Enum\SocialProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * « Continuer avec Facebook » — OAuth 2.0 via l'API Graph.
 *
 * Facebook n'est PAS un fournisseur OpenID Connect : il n'y a ni jeton
 * d'identité, ni « nonce ». On échange le code contre un jeton d'accès, puis on
 * interroge /me en énumérant les champs voulus — sans la liste `fields`,
 * l'API ne renvoie que l'identifiant et le nom.
 *
 * Console : https://developers.facebook.com (produit « Facebook Login »).
 * Deux exigences à connaître avant la mise en service :
 *  - l'application doit déclarer une URL de politique de confidentialité,
 *    laquelle n'existe pas encore sur ce site ;
 *  - l'e-mail n'est renvoyé que si l'utilisateur l'a accepté, et jamais s'il
 *    s'est inscrit sur Facebook avec un simple numéro de téléphone.
 */
final class FacebookOAuthProvider implements OAuthProviderInterface
{
    private const API_VERSION = 'v21.0';
    private const AUTHORIZE_URL = 'https://www.facebook.com/'.self::API_VERSION.'/dialog/oauth';
    private const TOKEN_URL = 'https://graph.facebook.com/'.self::API_VERSION.'/oauth/access_token';
    private const ME_URL = 'https://graph.facebook.com/'.self::API_VERSION.'/me';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(FACEBOOK_OAUTH_CLIENT_ID)%')] private readonly string $clientId,
        #[Autowire('%env(FACEBOOK_OAUTH_CLIENT_SECRET)%')] private readonly string $clientSecret,
        #[Autowire('%env(OAUTH_REDIRECT_BASE)%')] private readonly string $redirectBase,
    ) {
    }

    public function provider(): SocialProvider
    {
        return SocialProvider::Facebook;
    }

    public function isConfigured(): bool
    {
        return OAuthCredentials::areReal($this->clientId, $this->clientSecret);
    }

    public function usesFormPost(): bool
    {
        return false;
    }

    public function authorizationUrl(string $state, string $nonce): string
    {
        // Le nonce n'est pas utilisé : Facebook ne délivre pas de jeton
        // d'identité. Le paramètre « state » suffit à parer le rejeu.
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'email public_profile',
            'state' => $state,
        ], '', '&', \PHP_QUERY_RFC3986);
    }

    public function fetchUser(Request $request, string $nonce): SocialUser
    {
        $code = $request->query->get('code');

        if (!\is_string($code) || '' === $code) {
            throw new OAuthException(sprintf('Facebook n\'a pas renvoyé de code (erreur : %s / %s).', (string) $request->query->get('error', 'aucune'), (string) $request->query->get('error_description', '')));
        }

        $token = $this->exchangeCode($code);

        $response = $this->httpClient->request('GET', self::ME_URL, [
            'query' => [
                'fields' => 'id,email,first_name,last_name,picture.type(large)',
                'access_token' => $token,
                // Signature de la requête avec le secret : empêche l'usage d'un
                // jeton volé depuis une autre application.
                'appsecret_proof' => hash_hmac('sha256', $token, $this->clientSecret),
            ],
            'timeout' => 10,
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->toArray(false);

        if (200 !== $response->getStatusCode() || !\is_string($data['id'] ?? null)) {
            throw new OAuthException('Lecture du profil Facebook refusée : HTTP '.$response->getStatusCode());
        }

        $picture = null;
        if (isset($data['picture']) && \is_array($data['picture'])) {
            $inner = $data['picture']['data'] ?? null;
            if (\is_array($inner) && \is_string($inner['url'] ?? null)) {
                $picture = $inner['url'];
            }
        }

        $email = \is_string($data['email'] ?? null) ? $data['email'] : null;

        return new SocialUser(
            provider: SocialProvider::Facebook,
            externalId: $data['id'],
            email: $email,
            // Facebook ne transmet une adresse que confirmée de son côté. Sa
            // seule présence vaut donc vérification — l'absence, en revanche,
            // est fréquente et doit être traitée.
            emailVerified: null !== $email,
            firstName: \is_string($data['first_name'] ?? null) ? $data['first_name'] : null,
            lastName: \is_string($data['last_name'] ?? null) ? $data['last_name'] : null,
            avatarUrl: $picture,
        );
    }

    private function exchangeCode(string $code): string
    {
        $response = $this->httpClient->request('GET', self::TOKEN_URL, [
            'query' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $this->redirectUri(),
                'code' => $code,
            ],
            'timeout' => 10,
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->toArray(false);

        if (200 !== $response->getStatusCode() || !\is_string($data['access_token'] ?? null)) {
            throw new OAuthException('Échange du code Facebook refusé : HTTP '.$response->getStatusCode());
        }

        return $data['access_token'];
    }

    private function redirectUri(): string
    {
        return rtrim($this->redirectBase, '/').'/connexion/facebook/retour';
    }
}
