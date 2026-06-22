<?php

declare(strict_types=1);

namespace App\Tests\Notification\EventSubscriber;

use App\Catalog\Entity\Service;
use App\Notification\Entity\Notification;
use App\Notification\Enum\NotificationCategory;
use App\Notification\EventSubscriber\ReviewNotificationSubscriber;
use App\Notification\Service\NotificationService;
use App\Provider\Entity\ProviderProfile;
use App\Review\Entity\Review;
use App\Review\Event\ReviewAdded;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ReviewNotificationSubscriberTest extends TestCase
{
    public function testOnReviewAddedNotifiesTheProviderOwner(): void
    {
        $owner = new User();
        $service = (new Service())->setProvider((new ProviderProfile())->setUser($owner));
        $review = (new Review())->setService($service)->setRating(5);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::callback(
            static fn (object $n): bool => $n instanceof Notification
                && $n->getRecipient() === $owner
                && NotificationCategory::Review === $n->getCategory(),
        ));
        $em->expects(self::once())->method('flush');

        (new ReviewNotificationSubscriber(new NotificationService($em)))
            ->onReviewAdded(new ReviewAdded($review));
    }

    public function testDoesNothingWhenServiceHasNoProviderOwner(): void
    {
        // Service sans annonceur rattaché : pas de destinataire.
        $review = (new Review())->setService(new Service())->setRating(4);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        (new ReviewNotificationSubscriber(new NotificationService($em)))
            ->onReviewAdded(new ReviewAdded($review));
    }
}
