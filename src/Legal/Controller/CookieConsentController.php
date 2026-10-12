<?php

declare(strict_types=1);

namespace App\Legal\Controller;

use App\Legal\Enum\CookieCategory;
use App\Legal\Service\CookieConsentService;
use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Enregistre le choix du bandeau cookies (12/10) : tout accepter, tout
 * refuser ou choix par catégorie. Appelé en fetch par assets/cookie-consent.js
 * (réponse JSON, la page n'est pas rechargée) ou par un envoi de formulaire
 * classique sans JavaScript (retour sur la page d'origine).
 */
final class CookieConsentController extends AbstractController
{
    #[Route(path: ['fr' => '/cookies/consentement', 'en' => '/en/cookies/consent'], name: 'app_cookie_consent', methods: ['POST'])]
    public function decide(Request $request, CookieConsentService $consents): Response
    {
        if (!$this->isCsrfTokenValid('cookie_consent', (string) $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'csrf'], Response::HTTP_FORBIDDEN);
        }

        $user = $this->getUser() instanceof User ? $this->getUser() : null;
        $accepted = match ((string) $request->request->get('choice')) {
            'all' => CookieCategory::optional(),
            'none' => [],
            default => array_values(array_filter(array_map(
                static fn (mixed $c): ?CookieCategory => CookieCategory::tryFrom((string) $c),
                $request->request->all('categories'),
            ), static fn (?CookieCategory $c): bool => null !== $c && $c->isOptional())),
        };
        [$consent, $cookie] = $consents->decide($request, $accepted, $user);

        $response = $request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')
            ? new JsonResponse(['accepted' => $consent->getAcceptedCategories()])
            : new RedirectResponse($this->backUrl($request));

        return $consents->responseWithCookie($response, $cookie);
    }

    private function backUrl(Request $request): string
    {
        $referer = (string) $request->headers->get('Referer');

        return '' !== $referer && parse_url($referer, \PHP_URL_HOST) === $request->getHost() ? $referer : $this->generateUrl('app_home');
    }
}
