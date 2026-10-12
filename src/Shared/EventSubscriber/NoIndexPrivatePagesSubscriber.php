<?php

declare(strict_types=1);

namespace App\Shared\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * SEO (12/10) : les pages personnelles ou techniques ne doivent jamais
 * apparaître dans les résultats de recherche — espace client, espace pro,
 * back-office, connexion et parcours de mot de passe, assistants de création,
 * réservations, paiement. En-tête X-Robots-Tag, valable même pour une page
 * qu'un moteur aurait trouvée par un lien externe (robots.txt ne fait
 * qu'empêcher l'exploration).
 *
 * La liste suit les règles access_control de security.yaml, dans les deux
 * langues ; les pages d'acquisition (inscription pro) restent indexables.
 */
#[AsEventListener]
final class NoIndexPrivatePagesSubscriber
{
    private const PRIVATE_PATHS = '#^/(?:en/)?(?:'
        .'compte|account|admin|_profiler|login|logout|connexion|authentification|authentication'
        .'|mot-de-passe-oublie|forgot-password|verification-email|verify-email'
        .'|pro/(?!inscription$|signup$)'
        .'|evenements/creer|events/create|evenements/groupes/creer|events/groups/create'
        .'|creer-une-activite-gratuite|create-a-free-activity'
        .'|reservations|bookings|paiement/|payment/|cadeaux/offrir/paiement|gifts/give/payment|cadeaux/bon/|gifts/card/'
        .'|mes-favoris|my-favourites|aide/chat|help/chat|categories/(?:proposer|suggest)'
        .'|signalement|report|suggestions|newsletter'
        .')(?:/|$)'
        .'|/(?:participer|participants|invitation|join|invite)$#';

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        if (1 === preg_match(self::PRIVATE_PATHS, $event->getRequest()->getPathInfo())) {
            $event->getResponse()->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
    }
}
