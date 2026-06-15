<?php

declare(strict_types=1);

namespace App\Tests\Notification\Entity;

use App\Notification\Entity\Notification;
use App\Notification\Enum\NotificationCategory;
use App\User\Entity\User;
use PHPUnit\Framework\TestCase;

final class NotificationTest extends TestCase
{
    public function testIsUnreadByDefault(): void
    {
        $notification = new Notification();

        self::assertFalse($notification->isRead());
        self::assertNull($notification->getReadAt());
    }

    public function testMarkAsReadSetsReadAt(): void
    {
        $notification = new Notification();

        $notification->markAsRead();

        self::assertTrue($notification->isRead());
        self::assertInstanceOf(\DateTimeImmutable::class, $notification->getReadAt());
    }

    public function testMarkAsReadIsIdempotent(): void
    {
        $notification = new Notification();
        $notification->markAsRead();
        $first = $notification->getReadAt();

        $notification->markAsRead();

        self::assertSame($first, $notification->getReadAt());
    }

    public function testFieldsAreAssignable(): void
    {
        $recipient = new User();

        $notification = (new Notification())
            ->setRecipient($recipient)
            ->setCategory(NotificationCategory::Booking)
            ->setTitle('Réservation confirmée')
            ->setMessage('Votre réservation est confirmée.');

        self::assertSame($recipient, $notification->getRecipient());
        self::assertSame(NotificationCategory::Booking, $notification->getCategory());
        self::assertSame('Réservation confirmée', $notification->getTitle());
        self::assertSame('Votre réservation est confirmée.', $notification->getMessage());
    }
}
