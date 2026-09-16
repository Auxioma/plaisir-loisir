<?php

declare(strict_types=1);

namespace App\Tests\Notification\Presenter;

use App\Notification\Entity\Notification;
use App\Notification\Enum\NotificationCategory;
use App\Notification\Presenter\NotificationPresenter;
use PHPUnit\Framework\TestCase;

final class NotificationPresenterTest extends TestCase
{
    public function testGroupsByTodayAndYesterday(): void
    {
        $today = $this->notificationAt(new \DateTimeImmutable('-30 minutes'), NotificationCategory::Booking, 'Réservation confirmée');
        $yesterday = $this->notificationAt(new \DateTimeImmutable('-1 day'), NotificationCategory::Review, 'Nouvel avis');

        $groups = (new NotificationPresenter())->groups([$today, $yesterday]);

        self::assertCount(2, $groups);
        self::assertSame("Aujourd'hui", $groups[0]['section']);
        self::assertSame('Réservation confirmée', $groups[0]['items'][0]['title']);
        self::assertSame('Hier', $groups[1]['section']);
        self::assertSame('Nouvel avis', $groups[1]['items'][0]['title']);
    }

    public function testMapsCategoryToIconAndTone(): void
    {
        $notification = $this->notificationAt(new \DateTimeImmutable(), NotificationCategory::Payment, 'Paiement reçu');

        $groups = (new NotificationPresenter())->groups([$notification]);
        $item = $groups[0]['items'][0];

        self::assertSame('card', $item['icon']);
        self::assertSame('green', $item['tone']);
    }

    public function testMutedReflectsReadState(): void
    {
        $notification = $this->notificationAt(new \DateTimeImmutable(), NotificationCategory::System, 'Compte');
        $notification->markAsRead();

        $groups = (new NotificationPresenter())->groups([$notification]);

        self::assertTrue($groups[0]['items'][0]['muted']);
    }

    private function notificationAt(\DateTimeImmutable $createdAt, NotificationCategory $category, string $title): Notification
    {
        $notification = (new Notification())
            ->setCategory($category)
            ->setTitle($title)
            ->setMessage('Détail.');

        $property = new \ReflectionProperty(Notification::class, 'createdAt');
        $property->setAccessible(true);
        $property->setValue($notification, $createdAt);

        return $notification;
    }
}
