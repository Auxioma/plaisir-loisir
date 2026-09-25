<?php

declare(strict_types=1);

namespace App\Shared\Twig;

use App\Shared\Service\AccountBadgeCounter;
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
 * déjà pour `OAuthExtension`. Mêmes compteurs que les badges du menu compte
 * (`AccountIdentityPresenter`), partagés via `AccountBadgeCounter` pour
 * n'être calculés qu'une fois par page.
 */
final class UnreadCountsExtension extends AbstractExtension
{
    public function __construct(
        private readonly AccountBadgeCounter $badges,
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
        return null !== $user ? $this->badges->unreadMessages($user) : 0;
    }

    public function unreadNotifications(?User $user): int
    {
        return null !== $user ? $this->badges->unreadNotifications($user) : 0;
    }

    /**
     * Raccourci « Demandes reçues » (icône enveloppe du header, réservée à
     * ROLE_PROVIDER dans le template) : demandes ouvertes du métier du
     * prestataire qu'il n'a pas encore devisées.
     */
    public function pendingRequests(?User $user): int
    {
        return null !== $user ? $this->badges->pendingProviderRequests($user) : 0;
    }
}
