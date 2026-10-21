<?php

declare(strict_types=1);

namespace App\User\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationSuccessHandler;
use Symfony\Component\Security\Http\HttpUtils;

/** Redirige chaque profil vers son espace après authentification. */
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
        } elseif (!\in_array('ROLE_ADMIN', $token->getRoleNames(), true) && !\in_array('ROLE_PROVIDER', $token->getRoleNames(), true)) {
            $targetUrl = $this->urlGenerator->generate('app_account_dashboard');
        }

        return $this->httpUtils->createRedirectResponse($request, $targetUrl);
    }

    private static function isProSpace(string $url): bool
    {
        return 1 === preg_match('#^/(en/)?pro/#', (string) parse_url($url, \PHP_URL_PATH));
    }
}
