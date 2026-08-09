<?php

declare(strict_types=1);

namespace App\Shared\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Applique à chaque requête la langue choisie par l'utilisateur (mémorisée
 * en session par LocaleController). Sans choix explicite, la locale par
 * défaut (fr) s'applique.
 */
final class LocaleSubscriber implements EventSubscriberInterface
{
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->hasPreviousSession()) {
            return;
        }

        $locale = $request->getSession()->get('_locale');
        if (\is_string($locale) && '' !== $locale) {
            $request->setLocale($locale);
        }
    }

    public static function getSubscribedEvents(): array
    {
        // Priorité 20 : après l'initialisation de la session, avant que les
        // contrôleurs et Twig ne lisent la locale de la requête.
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }
}
