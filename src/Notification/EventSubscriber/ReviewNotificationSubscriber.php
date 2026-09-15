<?php

declare(strict_types=1);

namespace App\Notification\EventSubscriber;

use App\Notification\Enum\NotificationCategory;
use App\Notification\Service\NotificationService;
use App\Review\Event\ReviewAdded;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Notifie le prestataire lorsqu'il reçoit un nouvel avis.
 */
final class ReviewNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ReviewAdded::class => 'onReviewAdded',
        ];
    }

    public function onReviewAdded(ReviewAdded $event): void
    {
        $review = $event->getReview();
        $owner = $review->getProvider()?->getUser();
        if (null === $owner) {
            return;
        }

        $this->notifications->notify(
            $owner,
            NotificationCategory::Review,
            'Nouvel avis',
            \sprintf('Vous avez reçu un avis %d/5.', $review->getRating()),
        );
    }
}
