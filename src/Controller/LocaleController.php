<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Bascule de langue (sélecteurs Français/Anglais des en-têtes et du footer).
 * La locale est mémorisée en session (voir LocaleSubscriber) : les URLs ne
 * changent pas, toute la plateforme bascule.
 */
final class LocaleController extends AbstractController
{
    #[Route('/langue/{locale}', name: 'app_locale_switch', requirements: ['locale' => 'fr|en'])]
    public function switch(string $locale, Request $request): Response
    {
        $request->getSession()->set('_locale', $locale);

        // Retour à la page d'origine, uniquement si elle vient bien de chez
        // nous (pas de redirection ouverte vers un site externe).
        $referer = $request->headers->get('referer');
        if ($referer && str_starts_with($referer, $request->getSchemeAndHttpHost())) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_home');
    }
}
