<?php

declare(strict_types=1);

namespace App\User\OAuth;

use App\User\Enum\SocialProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * « Se connecter avec Apple » — OpenID Connect, avec trois singularités.
 *
 *  1. LE SECRET CLIENT N'EST PAS UNE CHAÎNE FIXE. C'est un jeton JWT signé en
 *     ES256 avec une clé privée elliptique (fichier .p8 téléchargé une seule
 *     fois depuis le portail Apple), valable six mois au maximum. Il est donc
 *     fabriqué à chaque échange, ci-dessous.
 *  2. LE RETOUR SE FAIT EN POST, pas en GET : dès qu'on demande le nom ou
 *     l'e-mail, Apple impose « response_mode=form_post ».
 *  3. LE NOM N'EST TRANSMIS QU'UNE SEULE FOIS, à la toute première
 *     autorisation, dans un champ « user » au format JSON. Si on ne
 *     l'enregistre pas à cet instant, il est définitivement perdu — les
 *     connexions suivantes ne renverront plus que l'identifiant et l'e-mail.
 *
 * Prérequis côté Apple, à anticiper : le programme développeur est payant
 * (99 $ par an), le domaine doit être vérifié, et l'adresse renvoyée est
 * souvent une adresse relais en @privaterelay.appleid.com — Apple laisse
 * l'utilisateur masquer la sienne.
 */
final class AppleOAuthProvider implements OAuthProviderInterface
{
    private const AUTHORIZE_URL = 'https://appleid.apple.com/auth/authorize';
    private const TOKEN_URL = 'https://appleid.apple.com/auth/token';
    private const ISSUER = 'https://appleid.apple.com';

    /** Durée de vie du secret client. Apple refuse au-delà de six mois. */
    private const SECRET_LIFETIME = 15552000;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        /** Identifiant de service (« Services ID »), pas l'identifiant d'application. */
        #[Autowire('%env(APPLE_OAUTH_CLIENT_ID)%')] private readonly string $clientId,
        #[Autowire('%env(APPLE_OAUTH_TEAM_ID)%')] private readonly string $teamId,
        #[Autowire('%env(APPLE_OAUTH_KEY_ID)%')] private readonly string $keyId,
        /** Chemin du fichier .p8 contenant la clé privée. */
        #[Autowire('%env(APPLE_OAUTH_PRIVATE_KEY_PATH)%')] private readonly string $privateKeyPath,
        #[Autowire('%env(OAUTH_REDIRECT_BASE)%')] private readonly string $redirectBase,
    ) {
    }

    public function provider(): SocialProvider
    {
        return SocialProvider::Apple;
    }

    public function isConfigured(): bool
    {
        return OAuthCredentials::areReal($this->clientId, $this->teamId, $this->keyId, $this->privateKeyPath)
            && is_readable($this->privateKeyPath);
    }

    public function usesFormPost(): bool
    {
        return true;
    }

    public function authorizationUrl(string $state, string $nonce): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'name email',
            'state' => $state,
            'nonce' => $nonce,
            'response_mode' => 'form_post',
        ], '', '&', \PHP_QUERY_RFC3986);
    }

    public function fetchUser(Request $request, string $nonce): SocialUser
    {
        // Retour en POST : le code arrive dans le corps de la requête.
        $code = $request->request->get('code');

        if (!\is_string($code) || '' === $code) {
            throw new OAuthException(sprintf('Apple n\'a pas renvoyé de code (erreur : %s).', (string) $request->request->get('error', 'aucune')));
        }

        $claims = $this->exchangeCode($code, $nonce);

        $sub = $claims['sub'] ?? null;

        if (!\is_string($sub) || '' === $sub) {
            throw new OAuthException('Jeton d\'identité Apple sans « sub ».');
        }

        [$firstName, $lastName] = $this->readNameOnce($request);

        $email = \is_string($claims['email'] ?? null) ? $claims['email'] : null;

        // Apple renvoie « email_verified » tantôt en booléen, tantôt en chaîne
        // « true » : les deux formes existent selon les cas.
        $verified = $claims['email_verified'] ?? false;
        $emailVerified = true === $verified || 'true' === $verified;

        return new SocialUser(
            provider: SocialProvider::Apple,
            externalId: $sub,
            email: $email,
            emailVerified: $emailVerified,
            firstName: $firstName,
            lastName: $lastName,
            // Apple ne fournit aucune photo de profil.
            avatarUrl: null,
        );
    }

    /**
     * Le nom, transmis une seule fois, à la première autorisation.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function readNameOnce(Request $request): array
    {
        $raw = $request->request->get('user');

        if (!\is_string($raw) || '' === $raw) {
            return [null, null];
        }

        $decoded = json_decode($raw, true);

        if (!\is_array($decoded) || !\is_array($decoded['name'] ?? null)) {
            return [null, null];
        }

        $name = $decoded['name'];

        return [
            \is_string($name['firstName'] ?? null) ? $name['firstName'] : null,
            \is_string($name['lastName'] ?? null) ? $name['lastName'] : null,
        ];
    }

    /**
     * Échange le code contre le jeton d'identité et en renvoie les revendications.
     *
     * @return array<string, mixed>
     */
    private function exchangeCode(string $code, string $nonce): array
    {
        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'body' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->buildClientSecret(),
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->redirectUri(),
            ],
            'timeout' => 10,
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->toArray(false);

        if (200 !== $response->getStatusCode() || !\is_string($data['id_token'] ?? null)) {
            throw new OAuthException(sprintf('Échange du code Apple refusé (HTTP %d) : %s', $response->getStatusCode(), (string) ($data['error'] ?? 'sans détail')));
        }

        return $this->readIdToken($data['id_token'], $nonce);
    }

    /**
     * Lit et contrôle le jeton d'identité.
     *
     * La SIGNATURE n'est volontairement pas revérifiée : ce jeton n'a pas
     * transité par le navigateur, il vient d'être obtenu de appleid.apple.com
     * sur un canal TLS authentifié. C'est le cas expressément prévu par la
     * spécification OpenID Connect (Core 1.0, §3.1.3.7, point 6), qui autorise
     * alors à s'en remettre à la sécurité du canal.
     *
     * Les revendications, elles, sont toutes contrôlées : émetteur,
     * destinataire, expiration et nonce. Le nonce est le point important — il
     * lie ce jeton à CETTE demande de connexion et interdit de rejouer un jeton
     * obtenu ailleurs.
     *
     * @return array<string, mixed>
     */
    private function readIdToken(string $idToken, string $nonce): array
    {
        $parts = explode('.', $idToken);

        if (3 !== \count($parts)) {
            throw new OAuthException('Jeton d\'identité Apple malformé.');
        }

        $payload = json_decode(self::base64UrlDecode($parts[1]), true);

        if (!\is_array($payload)) {
            throw new OAuthException('Charge utile du jeton Apple illisible.');
        }

        if (self::ISSUER !== ($payload['iss'] ?? null)) {
            throw new OAuthException('Émetteur inattendu dans le jeton Apple.');
        }

        // « aud » peut être une chaîne ou un tableau selon les cas.
        $aud = $payload['aud'] ?? null;
        $audiences = \is_array($aud) ? $aud : [$aud];

        if (!\in_array($this->clientId, $audiences, true)) {
            throw new OAuthException('Le jeton Apple ne nous est pas destiné.');
        }

        $exp = $payload['exp'] ?? 0;

        if (!\is_int($exp) || $exp <= time()) {
            throw new OAuthException('Jeton Apple expiré.');
        }

        if (($payload['nonce'] ?? null) !== $nonce) {
            throw new OAuthException('Nonce absent ou différent : jeton Apple rejeté.');
        }

        /* @var array<string, mixed> $payload */
        return $payload;
    }

    /**
     * Fabrique le secret client : un JWT signé en ES256 avec la clé .p8.
     */
    private function buildClientSecret(): string
    {
        $key = @file_get_contents($this->privateKeyPath);

        if (false === $key) {
            throw new OAuthException(sprintf('Clé privée Apple illisible : %s', $this->privateKeyPath));
        }

        $now = time();

        $header = ['alg' => 'ES256', 'kid' => $this->keyId, 'typ' => 'JWT'];
        $payload = [
            'iss' => $this->teamId,
            'iat' => $now,
            'exp' => $now + self::SECRET_LIFETIME,
            'aud' => self::ISSUER,
            'sub' => $this->clientId,
        ];

        $signingInput = self::base64UrlEncode(self::encodeJson($header))
            .'.'.self::base64UrlEncode(self::encodeJson($payload));

        $privateKey = openssl_pkey_get_private($key);

        if (false === $privateKey) {
            throw new OAuthException('Clé privée Apple invalide : '.(string) openssl_error_string());
        }

        $der = '';

        if (!openssl_sign($signingInput, $der, $privateKey, \OPENSSL_ALGO_SHA256)) {
            throw new OAuthException('Signature du secret Apple impossible : '.(string) openssl_error_string());
        }

        return $signingInput.'.'.self::base64UrlEncode(self::derToRawSignature($der));
    }

    /**
     * Convertit une signature ECDSA du format DER vers le format brut du JWT.
     *
     * openssl_sign produit une structure DER — une SÉQUENCE contenant deux
     * entiers R et S, de longueur variable, parfois précédés d'un octet nul
     * pour les garder positifs. Un JWT ES256 attend tout autre chose : R et S
     * bruts, complétés à gauche sur 32 octets chacun, soit 64 octets exactement.
     *
     * Sans cette conversion, Apple rejette le secret et la connexion échoue
     * avec un « invalid_client » parfaitement opaque.
     */
    private static function derToRawSignature(string $der): string
    {
        $offset = 0;

        if ("\x30" !== ($der[$offset] ?? '')) {
            throw new OAuthException('Signature ECDSA inattendue : séquence DER absente.');
        }
        ++$offset;

        // Longueur de la séquence. Le bit de poids fort indique la forme
        // longue : les 7 bits restants disent alors sur combien d'octets la
        // longueur elle-même est écrite. On saute ces octets, la longueur
        // totale ne nous servant pas — on lit les deux entiers à la suite.
        $lengthByte = \ord($der[$offset]);
        ++$offset;

        if (0 !== ($lengthByte & 0x80)) {
            $offset += $lengthByte & 0x7F;
        }

        $r = self::readDerInteger($der, $offset);
        $s = self::readDerInteger($der, $offset);

        return str_pad($r, 32, "\x00", \STR_PAD_LEFT).str_pad($s, 32, "\x00", \STR_PAD_LEFT);
    }

    private static function readDerInteger(string $der, int &$offset): string
    {
        if ("\x02" !== ($der[$offset] ?? '')) {
            throw new OAuthException('Signature ECDSA inattendue : entier DER absent.');
        }
        ++$offset;

        $length = \ord($der[$offset]);
        ++$offset;

        $value = substr($der, $offset, $length);
        $offset += $length;

        // L'octet nul de tête ne sert qu'à marquer le signe : il ne fait pas
        // partie du nombre et doit disparaître.
        return ltrim($value, "\x00");
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function encodeJson(array $data): string
    {
        return json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $padded = str_pad(strtr($value, '-_', '+/'), (int) (4 * ceil(\strlen($value) / 4)), '=');

        return (string) base64_decode($padded, true);
    }

    private function redirectUri(): string
    {
        return rtrim($this->redirectBase, '/').'/connexion/apple/retour';
    }
}
