<?php

declare(strict_types=1);

namespace App\Provider\EventSubscriber;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Un professionnel reste dans SON espace (05/10).
 *
 * Les écrans de l'espace particulier (/compte/*) s'affichaient pour un
 * compte pro dans la coquille du particulier, avec un menu pro recopié :
 * un troisième « profil », hors maquettes. Toute page /compte consultée par
 * un professionnel (GET) est désormais redirigée vers son équivalent dans
 * l'espace pro. Les envois de formulaire (POST) ne sont pas touchés, et la
 * confirmation de déconnexion reste commune.
 */
final class ProviderAccountRedirectSubscriber implements EventSubscriberInterface
{
    /** Route de l'espace particulier => [route de l'espace pro, paramètres repris]. */
    private const MAP = [
        'app_account_dashboard' => ['app_pro_dashboard', []],
        'app_account_notifications' => ['app_pro_notifications', ['filtre', 'q', 'type', 'page']],
        'app_account_notification_preferences' => ['app_pro_settings', []],
        'app_account_messages' => ['app_pro_messages', []],
        'app_account_messages_show' => ['app_pro_messages', []],
        'app_account_profile' => ['app_pro_profile_edit', []],
        'app_account_settings' => ['app_pro_settings', []],
        'app_account_payments' => ['app_pro_revenue', []],
        'app_account_support' => ['app_pro_support', []],
        'app_account_support_show' => ['app_pro_support_show', ['number']],
        'app_account_contact' => ['app_pro_support', []],
        'app_account_requests' => ['app_pro_requests', []],
        'app_account_requests_new' => ['app_pro_requests', []],
        'app_account_history' => ['app_pro_bookings', []],
        'app_account_events' => ['app_pro_events', []],
        'app_account_private_activities' => ['app_pro_activities', []],
        'app_account_private_activities_new' => ['app_pro_activities_new', []],
        'app_account_private_activity_wizard' => ['app_pro_activities_new', []],
        'app_account_favorites' => ['app_favorites_page', []],
        'app_account_favorites_list' => ['app_favorites_page', []],
        'app_account_referral' => ['app_pro_dashboard', []],
        'app_account_albums' => ['app_pro_dashboard', []],
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
        if (!$event->isMainRequest() || !$request->isMethod('GET')) {
            return;
        }

        $route = (string) $request->attributes->get('_route', '');
        $base = (string) preg_replace('/\.(fr|en)$/', '', $route);
        if (!isset(self::MAP[$base]) || !$this->security->isGranted('ROLE_PROVIDER')) {
            return;
        }

        [$target, $keep] = self::MAP[$base];
        $params = ['_locale' => $request->getLocale()];
        foreach ($keep as $name) {
            $value = $request->attributes->get($name) ?? $request->query->get($name);
            if (null !== $value && '' !== $value) {
                $params[$name] = $value;
            }
        }

        $event->setResponse(new RedirectResponse($this->urls->generate($target, $params)));
    }
}
