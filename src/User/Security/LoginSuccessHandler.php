<?php

declare(strict_types=1);

namespace App\User\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationSuccessHandler;
use Symfony\Component\Security\Http\HttpUtils;

/**
 * Envoie les administrateurs droit sur le back-office après connexion, et
 * les professionnels sur le tableau de bord de leur espace.
 *
 * POURQUOI CETTE CLASSE EXISTE
 * `default_target_path` (security.yaml) vaut `app_home` pour tout le monde :
 * un admin qui se connectait depuis /login atterrissait donc sur l'accueil
 * public, et devait taper /admin lui-même. Ça ne concerne que ce cas précis
 * — un admin déjà redirigé vers /login DEPUIS /admin (accès direct à une
 * page protégée sans session) continue d'y retourner normalement, la cible
 * mémorisée en session (TargetPathTrait) restant prioritaire ci-dessous.
 */
final class LoginSuccessHandler extends DefaultAuthenticationSuccessHandler
{
    public function __construct(
        HttpUtils $httpUtils,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
        parent::__construct($httpUtils);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $targetUrl = $this->determineTargetUrl($request);

        if ($targetUrl === $this->options['default_target_path'] && \in_array('ROLE_ADMIN', $token->getRoleNames(), true)) {
            $targetUrl = $this->urlGenerator->generate('admin');
        } elseif (\in_array('ROLE_PROVIDER', $token->getRoleNames(), true) && !self::isProSpace($targetUrl)) {
            // Professionnel : toujours son espace pro (11/10), sauf s'il venait
            // d'une page de cet espace. Revenir sur une page publique d'où il
            // s'était connecté le laissait croire à un compte client.
            $targetUrl = $this->urlGenerator->generate('app_pro_dashboard');
        }

        return $this->httpUtils->createRedirectResponse($request, $targetUrl);
    }

    private static function isProSpace(string $url): bool
    {
        return 1 === preg_match('#^/(en/)?pro/#', (string) parse_url($url, \PHP_URL_PATH));
    }
}
