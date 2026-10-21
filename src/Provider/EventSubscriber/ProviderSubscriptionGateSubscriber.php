<?php

declare(strict_types=1);

namespace App\Provider\EventSubscriber;

use App\Payment\Service\SubscriptionAccess;
use App\Provider\Repository\ProviderProfileRepository;
use App\User\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Pas d'abonnement en cours, pas d'espace professionnel (20/10).
 *
 * Toute page /pro renvoie vers /pro/abonnement, sauf celles qui servent à
 * s'abonner ou à garder son compte en ordre : abonnement, paramètres (dont
 * Stripe), profil et justificatifs, envoi du dossier, support, notifications,
 * et les réservations déjà prises, que le professionnel doit pouvoir honorer
 * (confirmation, règlement, contrôle du QR).
 *
 * Verrou inactif tant qu'aucune offre n'existe (SubscriptionAccess).
 */
final class ProviderSubscriptionGateSubscriber implements EventSubscriberInterface
{
    private const ALLOWED = [
        'app_pro_subscription',
        'app_pro_settings',
        'app_pro_stripe_',
        'app_pro_paypal_',
        'app_pro_my_profile',
        'app_pro_profile_edit',
        'app_pro_documents_',
        'app_pro_verification_submit',
        'app_pro_support',
        'app_pro_notifications',
        'app_pro_booking',
        'app_pro_login',
        'app_pro_register',
        'app_pro_forgot_password',
    ];

    public function __construct(
        private readonly Security $security,
        private readonly ProviderProfileRepository $providers,
        private readonly SubscriptionAccess $access,
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
        if (!str_starts_with($route, 'app_pro_')) {
            return;
        }
        foreach (self::ALLOWED as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return;
            }
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || !$this->security->isGranted('ROLE_PROVIDER')) {
            return;
        }
        $provider = $this->providers->findOneByUser($user);
        if (null === $provider || $this->access->hasAccess($provider)) {
            return;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('info', 'Choisissez une offre d’abonnement pour accéder à votre espace professionnel et recevoir des réservations.');
        }

        $event->setResponse(new RedirectResponse($this->urls->generate('app_pro_subscription', ['_locale' => $request->getLocale()])));
    }
}
