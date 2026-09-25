<?php

declare(strict_types=1);

namespace App\Tests\Shared\Service;

use App\Messaging\Repository\MessageRepository;
use App\Notification\Repository\NotificationRepository;
use App\Provider\Repository\ProviderProfileRepository;
use App\Quote\Repository\ServiceRequestRepository;
use App\Review\Repository\ReviewRepository;
use App\Shared\Service\AccountBadgeCounter;
use App\User\Entity\User;
use PHPUnit\Framework\TestCase;

final class AccountBadgeCounterTest extends TestCase
{
    /**
     * Le header et le menu du compte lisent les mêmes badges sur une même
     * page : une seule requête par compteur, pas une par appelant.
     */
    public function testCountsAreComputedOncePerRequest(): void
    {
        $user = new User();

        $notifications = $this->createMock(NotificationRepository::class);
        $notifications->expects(self::once())->method('countUnread')->with($user)->willReturn(3);

        $profiles = $this->createMock(ProviderProfileRepository::class);
        $profiles->expects(self::once())->method('findOneByUser')->willReturn(null);

        $counter = $this->counter($notifications, $profiles);

        self::assertSame(3, $counter->unreadNotifications($user));
        self::assertSame(3, $counter->unreadNotifications($user));

        // Client sans profil prestataire : les deux badges pro valent 0, et
        // le profil n'est cherché qu'une fois pour les deux.
        self::assertSame(0, $counter->pendingProviderRequests($user));
        self::assertSame(0, $counter->pendingProviderReviews($user));
    }

    public function testResetForgetsTheCountsBetweenRequests(): void
    {
        $user = new User();

        $notifications = $this->createMock(NotificationRepository::class);
        $notifications->expects(self::exactly(2))->method('countUnread')->willReturnOnConsecutiveCalls(3, 0);

        $counter = $this->counter($notifications, $this->createStub(ProviderProfileRepository::class));

        self::assertSame(3, $counter->unreadNotifications($user));
        $counter->reset();
        self::assertSame(0, $counter->unreadNotifications($user));
    }

    private function counter(NotificationRepository $notifications, ProviderProfileRepository $profiles): AccountBadgeCounter
    {
        return new AccountBadgeCounter(
            $this->createStub(MessageRepository::class),
            $notifications,
            $profiles,
            $this->createStub(ServiceRequestRepository::class),
            $this->createStub(ReviewRepository::class),
        );
    }
}
