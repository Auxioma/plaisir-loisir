<?php

declare(strict_types=1);

namespace App\Shared\EventSubscriber;

use App\I18n\Routing\LocaleUrlGenerator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Repli de langue pour les rares adresses qui n'en portent pas.
 *
 * Depuis le passage aux URLs traduites, c'est l'ADRESSE qui decide de la
 * langue : /activites est francais, /en/activities est anglais. Un moteur de
 * recherche, qui n'a pas de session, obtient donc toujours la bonne langue.
 *
 * Restent quelques routes techniques sans variante traduite (pare-feu,
 * retours OAuth, webhook) : pour elles seulement, on rejoue la derniere
 * langue choisie, memorisee ici au fil des pages. Ce souvenir ne peut jamais
 * contredire une langue presente dans l'URL.
 */
final class LocaleSubscriber implements EventSubscriberInterface
{
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->hasPreviousSession()) {
            return;
        }

        $session = $request->getSession();

        // L'URL porte une langue : elle fait foi, on la retient pour la suite.
        if ($request->attributes->has('_locale')) {
            $session->set('_locale', $request->getLocale());

            return;
        }

        // Adresse technique : on rejoue le dernier choix de l'utilisateur.
        $remembered = $session->get('_locale');
        if (\is_string($remembered) && \in_array($remembered, LocaleUrlGenerator::LOCALES, true)) {
            $request->setLocale($remembered);
        }
    }

    public static function getSubscribedEvents(): array
    {
        // Priorite 15 : juste apres le LocaleListener de Symfony (16), qui a
        // deja applique la langue lue dans l'URL. Passer avant lui reviendrait
        // a se faire ecraser.
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 15]],
        ];
    }
}
