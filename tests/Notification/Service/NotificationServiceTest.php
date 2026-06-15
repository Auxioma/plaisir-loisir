<?php

declare(strict_types=1);

namespace App\Tests\Notification\Service;

use App\Notification\Entity\Notification;
use App\Notification\Enum\NotificationCategory;
use App\Notification\Service\NotificationService;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class NotificationServiceTest extends TestCase
{
    public function testNotifyCreatesUnreadNotification(): void
    {
        $recipient = new User();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(Notification::class));
        $em->expects(self::once())->method('flush');

        $notification = (new NotificationService($em))->notify(
            $recipient,
            NotificationCategory::Review,
            'Nouvel avis',
            'Vous avez reçu un nouvel avis.',
        );

        self::assertSame($recipient, $notification->getRecipient());
        self::assertSame(NotificationCategory::Review, $notification->getCategory());
        self::assertSame('Nouvel avis', $notification->getTitle());
        self::assertFalse($notification->isRead());
    }

    public function testMarkAsReadFlushesAndMarksRead(): void
    {
        $notification = new Notification();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        (new NotificationService($em))->markAsRead($notification);

        self::assertTrue($notification->isRead());
    }
}
