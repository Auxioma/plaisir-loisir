<?php

declare(strict_types=1);

namespace App\Provider\EventSubscriber;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Un professionnel ne crée rien de gratuit (retours client du 11/10).
 *
 * Activités gratuites, événements et groupes entre membres sont réservés aux
 * particuliers ; le professionnel commercialise ses prestations depuis son
 * espace. Toute page de création de ce contenu (GET comme POST, assistants
 * compris) le renvoie vers l'assistant de dépôt d'une activité payante.
 *
 * La participation, elle, est refusée là où elle se décide :
 * PrivateActivityService::requestParticipation() et EventsController::join().
 */
final class ProviderFreeContentGuardSubscriber implements EventSubscriberInterface
{
    private const ROUTES = [
        'app_create_free',
        'app_account_private_activities_new',
        'app_account_private_activity_resume',
        'app_account_private_activity_wizard',
        'app_event_guide',
        'app_event_create',
        'app_event_resume',
        'app_event_contacts',
        'app_event_create_success',
        'app_group_create',
        'app_group_create_success',
    ];

    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Après le pare-feu (priorité 8) : l'utilisateur est connu.
        return [KernelEvents::REQUEST => ['onRequest', 0]];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest()) {
            return;
        }

        $route = (string) preg_replace('/\.(fr|en)$/', '', (string) $request->attributes->get('_route', ''));
        if (!\in_array($route, self::ROUTES, true) || !$this->security->isGranted('ROLE_PROVIDER')) {
            return;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('info', 'Votre compte professionnel propose des activités payantes : les activités gratuites et les événements entre membres sont réservés aux particuliers.');
        }

        $event->setResponse(new RedirectResponse($this->urls->generate('app_pro_activities_new', ['_locale' => $request->getLocale()])));
    }
}
