<?php

declare(strict_types=1);

namespace App\Shared\Twig;

use App\Messaging\Repository\MessageRepository;
use App\Notification\Repository\NotificationRepository;
use App\Provider\Repository\ProviderProfileRepository;
use App\Quote\Repository\ServiceRequestRepository;
use App\User\Entity\User;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Compteurs de non-lus (messages, notifications, demandes reçues) pour les
 * raccourcis du header (`_partials/navbar.html.twig`).
 *
 * Le header est inclus depuis `base.html.twig` sur toutes les pages, donc
 * par des dizaines de contrôleurs différents : leur passer la variable
 * obligerait à modifier chacun d'eux, comme `social_login_enabled` l'évite
 * déjà pour `OAuthExtension`. Mêmes requêtes que celles qui alimentent déjà
 * les badges du menu compte (`AccountIdentityPresenter`), pour rester
 * identiques.
 */
final class UnreadCountsExtension extends AbstractExtension
{
    public function __construct(
        private readonly MessageRepository $messages,
        private readonly NotificationRepository $notifications,
        private readonly ProviderProfileRepository $providerProfiles,
        private readonly ServiceRequestRepository $serviceRequests,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('unread_messages_count', $this->unreadMessages(...)),
            new TwigFunction('unread_notifications_count', $this->unreadNotifications(...)),
            new TwigFunction('pending_requests_count', $this->pendingRequests(...)),
        ];
    }

    /**
     * `?User $user` : le mode simulation `?connecte=1` (voir CLAUDE.md) affiche
     * le header connecté sans utilisateur réel, `app.user` reste alors null.
     */
    public function unreadMessages(?User $user): int
    {
        return null !== $user ? $this->messages->countUnreadForUser($user) : 0;
    }

    public function unreadNotifications(?User $user): int
    {
        return null !== $user ? $this->notifications->countUnread($user) : 0;
    }

    /**
     * Raccourci « Demandes reçues » (icône enveloppe du header, réservée à
     * ROLE_PROVIDER dans le template) : demandes ouvertes du métier du
     * prestataire qu'il n'a pas encore devisées.
     */
    public function pendingRequests(?User $user): int
    {
        if (null === $user) {
            return 0;
        }

        $profile = $this->providerProfiles->findOneByUser($user);
        $category = $profile?->getMainCategory();

        if (null === $profile || null === $category) {
            return 0;
        }

        return $this->serviceRequests->countOpenUnquotedForProvider($category, $profile);
    }
}
