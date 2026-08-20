<?php

declare(strict_types=1);

namespace App\User\OAuth;

use App\User\Enum\SocialProvider;

/**
 * Point d'accès unique aux trois fournisseurs.
 *
 * Les trois sont injectés nommément plutôt que par un service étiqueté : ils
 * sont trois, ils le resteront, et une liste explicite se lit sans connaître
 * les mécanismes d'étiquetage du conteneur.
 */
final class OAuthProviderRegistry
{
    public function __construct(
        private readonly GoogleOAuthProvider $google,
        private readonly FacebookOAuthProvider $facebook,
        private readonly AppleOAuthProvider $apple,
    ) {
    }

    public function get(SocialProvider $provider): OAuthProviderInterface
    {
        return match ($provider) {
            SocialProvider::Google => $this->google,
            SocialProvider::Facebook => $this->facebook,
            SocialProvider::Apple => $this->apple,
        };
    }

    /**
     * @return list<OAuthProviderInterface>
     */
    public function all(): array
    {
        return [$this->google, $this->facebook, $this->apple];
    }

    /**
     * Fournisseurs réellement utilisables, par valeur d'énumération.
     *
     * Sert au gabarit Twig : un bouton dont le fournisseur n'est pas configuré
     * reste inactif, plutôt que de conduire à une erreur du côté de Google.
     *
     * @return array<string, bool>
     */
    public function availability(): array
    {
        $state = [];

        foreach ($this->all() as $provider) {
            $state[$provider->provider()->value] = $provider->isConfigured();
        }

        return $state;
    }

    public function isConfigured(SocialProvider $provider): bool
    {
        return $this->get($provider)->isConfigured();
    }
}
