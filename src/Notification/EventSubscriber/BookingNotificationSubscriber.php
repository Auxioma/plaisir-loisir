<?php

declare(strict_types=1);

namespace App\Notification\EventSubscriber;

use App\Booking\Entity\Booking;
use App\Notification\Enum\NotificationCategory;
use App\Notification\Service\NotificationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\EnteredEvent;

/**
 * Crée une notification in-app pour le client à chaque étape clé du workflow
 * de réservation. Réagit aux événements émis automatiquement par Symfony Workflow.
 */
final class BookingNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.booking.entered.confirmed' => 'onConfirmed',
            'workflow.booking.entered.completed' => 'onCompleted',
            'workflow.booking.entered.cancelled' => 'onCancelled',
        ];
    }

    /**
     * @param EnteredEvent<object> $event
     */
    public function onConfirmed(EnteredEvent $event): void
    {
        $this->notifyClient($event, 'Réservation confirmée', 'Votre réservation est confirmée. À bientôt !');
    }

    /**
     * @param EnteredEvent<object> $event
     */
    public function onCompleted(EnteredEvent $event): void
    {
        $this->notifyClient($event, 'Réservation terminée', 'Votre activité est terminée — pensez à laisser un avis !');
    }

    /**
     * @param EnteredEvent<object> $event
     */
    public function onCancelled(EnteredEvent $event): void
    {
        $this->notifyClient($event, 'Réservation annulée', 'Votre réservation a été annulée.');
    }

    /**
     * @param EnteredEvent<object> $event
     */
    private function notifyClient(EnteredEvent $event, string $title, string $message): void
    {
        $booking = $event->getSubject();
        if (!$booking instanceof Booking) {
            return;
        }

        $client = $booking->getClient();
        if (null === $client) {
            return;
        }

        $this->notifications->notify($client, NotificationCategory::Booking, $title, $message);
    }
}
