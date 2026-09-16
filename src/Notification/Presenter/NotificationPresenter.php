<?php

declare(strict_types=1);

namespace App\Notification\Presenter;

use App\Notification\Entity\Notification;
use App\Notification\Enum\NotificationCategory;

/**
 * Transforme les entités Notification dans la forme groupée par section
 * temporelle (« Aujourd'hui », « Hier », …) attendue par
 * account/notifications.html.twig — reprend la forme de
 * StaticAccount::notifications(), retirée une fois l'écran reconnecté.
 *
 * Les libellés produits (titre/message stockés sur l'entité, texte relatif
 * ici) sont en français, comme le sont déjà ceux écrits par
 * BookingNotificationSubscriber et ReviewNotificationSubscriber : la
 * notification elle-même n'est pas encore traduite, il serait incohérent de
 * ne traduire que l'horodatage.
 */
final class NotificationPresenter
{
    /** @var array<string, string> */
    private const TONE_BY_CATEGORY = [
        NotificationCategory::Booking->value => 'violet',
        NotificationCategory::Review->value => 'yellow',
        NotificationCategory::Payment->value => 'green',
        NotificationCategory::Messaging->value => 'blue',
        NotificationCategory::System->value => 'violet',
        NotificationCategory::Quote->value => 'green',
        NotificationCategory::Activity->value => 'blue',
    ];

    /** @var array<string, string> */
    private const ICON_BY_CATEGORY = [
        NotificationCategory::Booking->value => 'calendar_check',
        NotificationCategory::Review->value => 'star',
        NotificationCategory::Payment->value => 'card',
        NotificationCategory::Messaging->value => 'mail',
        NotificationCategory::System->value => 'gear',
        NotificationCategory::Quote->value => 'receipt',
        NotificationCategory::Activity->value => 'users',
    ];

    /**
     * @param Notification[] $notifications déjà triées, plus récente d'abord
     *
     * @return list<array{section: string, items: list<array<string, mixed>>}>
     */
    public function groups(array $notifications): array
    {
        $now = new \DateTimeImmutable();

        $sections = [];
        foreach ($notifications as $notification) {
            $label = $this->sectionLabel($notification->getCreatedAt() ?? $now, $now);
            $sections[$label][] = $this->item($notification, $now);
        }

        $groups = [];
        foreach ($sections as $label => $items) {
            $groups[] = ['section' => $label, 'items' => $items];
        }

        return $groups;
    }

    /**
     * Liste plate, sans regroupement par jour — pour le widget « Notifications
     * récentes » du tableau de bord (§7.1 du CDC), plus court que l'écran
     * dédié.
     *
     * @param Notification[] $notifications déjà triées, plus récente d'abord
     *
     * @return list<array<string, mixed>>
     */
    public function items(array $notifications): array
    {
        $now = new \DateTimeImmutable();

        return array_map(fn (Notification $n): array => $this->item($n, $now), $notifications);
    }

    /**
     * @return array{icon: string, tone: string, title: string, detail: string, time: string, thumb: null, muted: bool}
     */
    private function item(Notification $notification, \DateTimeImmutable $now): array
    {
        $category = $notification->getCategory()->value;

        return [
            'icon' => self::ICON_BY_CATEGORY[$category],
            'tone' => self::TONE_BY_CATEGORY[$category],
            'title' => $notification->getTitle(),
            'detail' => $notification->getMessage(),
            'time' => $this->relativeTime($notification->getCreatedAt() ?? $now, $now),
            'thumb' => null,
            'muted' => $notification->isRead(),
        ];
    }

    private function sectionLabel(\DateTimeImmutable $date, \DateTimeImmutable $now): string
    {
        $day = $date->format('Y-m-d');

        return match ($day) {
            $now->format('Y-m-d') => "Aujourd'hui",
            $now->modify('-1 day')->format('Y-m-d') => 'Hier',
            default => $this->formatDate($date),
        };
    }

    private function relativeTime(\DateTimeImmutable $date, \DateTimeImmutable $now): string
    {
        $diffInSeconds = $now->getTimestamp() - $date->getTimestamp();

        if ($diffInSeconds < 60) {
            return "À l'instant";
        }

        if ($diffInSeconds < 3600) {
            $minutes = intdiv($diffInSeconds, 60);

            return \sprintf('Il y a %d minute%s', $minutes, $minutes > 1 ? 's' : '');
        }

        if ($date->format('Y-m-d') === $now->format('Y-m-d')) {
            $hours = intdiv($diffInSeconds, 3600);

            return \sprintf('Il y a %d heure%s', $hours, $hours > 1 ? 's' : '');
        }

        if ($date->format('Y-m-d') === $now->modify('-1 day')->format('Y-m-d')) {
            return 'Hier à '.$date->format('H:i');
        }

        return $this->formatDate($date).' à '.$date->format('H:i');
    }

    private function formatDate(\DateTimeImmutable $date): string
    {
        return (string) \IntlDateFormatter::formatObject($date, 'd MMMM y', \Locale::getDefault());
    }
}
