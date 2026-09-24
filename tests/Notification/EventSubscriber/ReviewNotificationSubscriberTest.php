<?php

declare(strict_types=1);

namespace App\Tests\Notification\EventSubscriber;

use App\Notification\Entity\Notification;
use App\Notification\Enum\NotificationCategory;
use App\Notification\EventSubscriber\ReviewNotificationSubscriber;
use App\Notification\Repository\NotificationRepository;
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
        $provider = (new ProviderProfile())->setUser($owner);
        $review = (new Review())->setProvider($provider)->setRating(5);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::callback(
            static fn (object $n): bool => $n instanceof Notification
                && $n->getRecipient() === $owner
                && NotificationCategory::Review === $n->getCategory(),
        ));
        $em->expects(self::once())->method('flush');

        (new ReviewNotificationSubscriber(new NotificationService($em, $this->createStub(NotificationRepository::class))))
            ->onReviewAdded(new ReviewAdded($review));
    }

    public function testDoesNothingWhenProviderHasNoOwner(): void
    {
        // Prestataire sans utilisateur rattaché : pas de destinataire.
        $review = (new Review())->setProvider(new ProviderProfile())->setRating(4);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        (new ReviewNotificationSubscriber(new NotificationService($em, $this->createStub(NotificationRepository::class))))
            ->onReviewAdded(new ReviewAdded($review));
    }
}
