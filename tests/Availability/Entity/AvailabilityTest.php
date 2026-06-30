<?php

declare(strict_types=1);

namespace App\Tests\Availability\Entity;

use App\Availability\Entity\Availability;
use PHPUnit\Framework\TestCase;

final class AvailabilityTest extends TestCase
{
    public function testDefaultsAndRemainingSeats(): void
    {
        $slot = (new Availability())->setCapacity(10);

        self::assertSame(0, $slot->getBooked());
        self::assertSame(10, $slot->getRemainingSeats());
        self::assertTrue($slot->isBookable());
    }

    public function testReserveReducesRemaining(): void
    {
        $slot = (new Availability())->setCapacity(5);

        $slot->reserve(3);

        self::assertSame(3, $slot->getBooked());
        self::assertSame(2, $slot->getRemainingSeats());
    }

    public function testReserveRejectsOverCapacity(): void
    {
        $slot = (new Availability())->setCapacity(2);

        $this->expectException(\InvalidArgumentException::class);

        $slot->reserve(3);
    }

    public function testReserveRejectsInvalidSeats(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Availability())->setCapacity(5)->reserve(0);
    }

    public function testReleaseFreesSeatsWithoutGoingNegative(): void
    {
        $slot = (new Availability())->setCapacity(5);
        $slot->reserve(2);

        $slot->release(5); // plus que réservé : reste à 0

        self::assertSame(0, $slot->getBooked());
    }

    public function testNotBookableWhenFull(): void
    {
        $slot = (new Availability())->setCapacity(1);
        $slot->reserve(1);

        self::assertFalse($slot->isBookable());
        self::assertSame(0, $slot->getRemainingSeats());
    }
}
