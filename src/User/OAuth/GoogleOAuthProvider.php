<?php

declare(strict_types=1);

namespace App\User\OAuth;

use App\User\Enum\SocialProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * « Continuer avec Google » — OpenID Connect.
 *
 * Google est le plus simple des trois : OpenID Connect complet, et un point
 * d'accès « userinfo » qui renvoie l'identité au format standard. On n'a donc
 * même pas besoin de lire le jeton d'identité nous-mêmes.
 *
 * Console d'administration : https://console.cloud.google.com/apis/credentials
 * (identifiant OAuth de type « Application Web »). L'URL de retour doit y être
 * déclarée AU CARACTÈRE PRÈS, sinon Google refuse avec « redirect_uri_mismatch ».
 */
final class GoogleOAuthProvider implements OAuthProviderInterface
{
    private const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(GOOGLE_OAUTH_CLIENT_ID)%')] private readonly string $clientId,
        #[Autowire('%env(GOOGLE_OAUTH_CLIENT_SECRET)%')] private readonly string $clientSecret,
        #[Autowire('%env(OAUTH_REDIRECT_BASE)%')] private readonly string $redirectBase,
    ) {
    }

    public function provider(): SocialProvider
    {
        return SocialProvider::Google;
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
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            // « openid » donne l'identifiant stable, les deux autres le profil.
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            // Sans « prompt=select_account », un utilisateur déjà connecté à un
            // compte Google est reconnecté sans pouvoir en changer.
            'prompt' => 'select_account',
        ], '', '&', \PHP_QUERY_RFC3986);
    }

    public function fetchUser(Request $request, string $nonce): SocialUser
    {
        $code = $request->query->get('code');

        if (!\is_string($code) || '' === $code) {
            throw new OAuthException(sprintf('Google n\'a pas renvoyé de code (erreur : %s).', (string) $request->query->get('error', 'aucune')));
        }

        $token = $this->exchangeCode($code);

        $response = $this->httpClient->request('GET', self::USERINFO_URL, [
            'auth_bearer' => $token,
            'timeout' => 10,
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new OAuthException('Google a refusé la lecture du profil : HTTP '.$response->getStatusCode());
        }

        /** @var array<string, mixed> $data */
        $data = $response->toArray(false);

        $sub = $data['sub'] ?? null;

        if (!\is_string($sub) || '' === $sub) {
            throw new OAuthException('Réponse Google sans identifiant « sub ».');
        }

        return new SocialUser(
            provider: SocialProvider::Google,
            externalId: $sub,
            email: \is_string($data['email'] ?? null) ? $data['email'] : null,
            // Google renvoie un vrai booléen ; on ne se contente pas de la
            // présence de l'adresse, une adresse non vérifiée ne vaut rien.
            emailVerified: true === ($data['email_verified'] ?? false),
            firstName: \is_string($data['given_name'] ?? null) ? $data['given_name'] : null,
            lastName: \is_string($data['family_name'] ?? null) ? $data['family_name'] : null,
            avatarUrl: \is_string($data['picture'] ?? null) ? $data['picture'] : null,
        );
    }

    private function exchangeCode(string $code): string
    {
        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'body' => [
                'code' => $code,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $this->redirectUri(),
                'grant_type' => 'authorization_code',
            ],
            'timeout' => 10,
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->toArray(false);

        if (200 !== $response->getStatusCode() || !\is_string($data['access_token'] ?? null)) {
            throw new OAuthException(sprintf('Échange du code Google refusé (HTTP %d) : %s', $response->getStatusCode(), (string) ($data['error_description'] ?? $data['error'] ?? 'sans détail')));
        }

        return $data['access_token'];
    }

    private function redirectUri(): string
    {
        return rtrim($this->redirectBase, '/').'/connexion/google/retour';
    }
}
