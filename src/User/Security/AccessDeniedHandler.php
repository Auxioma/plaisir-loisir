<?php

declare(strict_types=1);

namespace App\User\Security;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

/**
 * Renvoie vers son propre espace l'utilisateur connecté qui ouvre une page
 * à laquelle son compte n'a pas droit (ex. un client ou un prestataire sur
 * /admin).
 *
 * POURQUOI CETTE CLASSE EXISTE
 * Sans elle, Symfony répondait par une erreur 403 brute : page d'exception
 * en dev, page d'erreur générique en prod, sans aucun moyen de repartir.
 *
 * Ne concerne que les zones interdites par `access_control`, et que les
 * comptes connectés par mot de passe : une session
 * rouverte par « se souvenir de moi » est d'abord renvoyée à la connexion
 * par Symfony lui-même, qui n'appelle pas cette classe. Les appels AJAX/JSON
 * (favoris…) gardent leur 403 : le JavaScript attend un code d'erreur, pas
 * une page HTML de redirection.
 */
final class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        // Seuls les refus d'access_control (security.yaml) — une ZONE du site
        // interdite à ce type de compte — sont redirigés ; le pare-feu les
        // signale avec la requête pour sujet. Un refus sur une ressource
        // précise (voter : la conversation ou le devis d'un autre) garde son
        // 403 : c'est une tentative d'accès aux données d'autrui, pas une
        // erreur de navigation.
        if (!$accessDeniedException->getSubject() instanceof Request) {
            return null;
        }

        if ($request->isXmlHttpRequest() || 'json' === $request->getPreferredFormat()) {
            return null;
        }

        $route = $this->security->isGranted('ROLE_PROVIDER') ? 'app_pro_dashboard' : 'app_account_dashboard';
        $target = $this->urlGenerator->generate($route);

        // Accès refusé sur l'espace lui-même : rediriger bouclerait.
        if ($request->getPathInfo() === parse_url($target, \PHP_URL_PATH)) {
            return null;
        }

        $session = $request->hasSession() ? $request->getSession() : null;

        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('error', 'Vous n\'avez pas accès à cette page.');
        }

        return new RedirectResponse($target);
    }
}
