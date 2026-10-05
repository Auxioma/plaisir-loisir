<?php

declare(strict_types=1);

namespace App\Notification\Service;

use App\Notification\Entity\Notification;
use App\Notification\Presenter\NotificationPresenter;
use App\Notification\Repository\NotificationPreferenceRepository;
use App\Notification\Repository\NotificationRepository;
use App\User\Entity\User;
use Symfony\Component\HttpFoundation\Request;

/**
 * Liste des notifications d'un membre (onglets Toutes / Non lues / Archives,
 * recherche, type, 8 par page), partagée par l'espace particulier
 * (/compte/notifications) et l'espace professionnel (/pro/notifications).
 */
final class NotificationFeed
{
    public const PER_PAGE = 8;

    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly NotificationPresenter $presenter,
        private readonly NotificationPreferenceRepository $preferences,
    ) {
    }

    /**
     * @return array<string, mixed> variables de gabarit
     */
    public function page(User $user, Request $request): array
    {
        $all = $this->notifications->findByRecipient($user);
        $unread = \count(array_filter($all, static fn (Notification $n): bool => !$n->isRead()));

        $filter = (string) $request->query->get('filtre', 'toutes');
        if (!\in_array($filter, ['toutes', 'non-lues', 'archives'], true)) {
            $filter = 'toutes';
        }
        $query = trim((string) $request->query->get('q', ''));
        $type = (string) $request->query->get('type', '');

        $filtered = array_values(array_filter($all, static fn (Notification $n): bool => match ($filter) {
            'non-lues' => !$n->isRead(),
            'archives' => $n->isRead(),
            default => true,
        }
            && ('' === $type || $n->getCategory()->value === $type)
            && ('' === $query || false !== mb_stripos($n->getTitle().' '.$n->getMessage(), $query))));

        $total = \count($filtered);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($pages, max(1, $request->query->getInt('page', 1)));

        return [
            'filter' => $filter,
            'query' => $query,
            'type' => $type,
            'unread' => $unread,
            'items' => $this->presenter->items(\array_slice($filtered, ($page - 1) * self::PER_PAGE, self::PER_PAGE)),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'per_page' => self::PER_PAGE,
            'email_enabled' => $this->preferences->findOneByUser($user)?->isEmailEnabled() ?? true,
        ];
    }
}
