<?php

declare(strict_types=1);

namespace App\Tests\Notification\EventSubscriber;

use App\Booking\Entity\Booking;
use App\Notification\Entity\Notification;
use App\Notification\Enum\NotificationCategory;
use App\Notification\EventSubscriber\BookingNotificationSubscriber;
use App\Notification\Service\NotificationService;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Event\EnteredEvent;
use Symfony\Component\Workflow\Marking;

final class BookingNotificationSubscriberTest extends TestCase
{
    public function testOnConfirmedNotifiesTheClient(): void
    {
        $client = new User();
        $booking = (new Booking())->setClient($client);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::callback(
            static fn (object $n): bool => $n instanceof Notification
                && $n->getRecipient() === $client
                && NotificationCategory::Booking === $n->getCategory(),
        ));
        $em->expects(self::once())->method('flush');

        (new BookingNotificationSubscriber(new NotificationService($em)))
            ->onConfirmed(new EnteredEvent($booking, new Marking()));
    }

    public function testDoesNothingWhenBookingHasNoClient(): void
    {
        $booking = new Booking(); // aucun client

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        (new BookingNotificationSubscriber(new NotificationService($em)))
            ->onCancelled(new EnteredEvent($booking, new Marking()));
    }
}
