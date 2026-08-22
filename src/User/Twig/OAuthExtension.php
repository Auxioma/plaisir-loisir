<?php

declare(strict_types=1);

namespace App\User\Twig;

use App\User\Enum\SocialProvider;
use App\User\OAuth\OAuthProviderRegistry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose aux gabarits l'état des connexions sociales.
 *
 * Le partial des boutons est inclus par trois écrans qui n'ont pas tous le même
 * contrôleur : passer l'information en variable obligerait à modifier chacun.
 * Une fonction Twig évite cela.
 */
final class OAuthExtension extends AbstractExtension
{
    public function __construct(
        private readonly OAuthProviderRegistry $registry,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('social_login_enabled', $this->isEnabled(...)),
        ];
    }

    /**
     * Le bouton doit-il être actif ?
     *
     * Faux tant que les identifiants d'application sont ceux de démonstration :
     * le bouton reste alors inactif, exactement comme il l'était avant le
     * câblage. Rien ne change à l'écran tant que le CTO n'a pas fourni les clés.
     */
    public function isEnabled(string $provider): bool
    {
        $case = SocialProvider::tryFrom($provider);

        return null !== $case && $this->registry->isConfigured($case);
    }
}
