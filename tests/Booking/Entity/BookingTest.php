<?php

declare(strict_types=1);

namespace App\Tests\Booking\Entity;

use App\Booking\Entity\Booking;
use App\Booking\Entity\BookingItem;
use App\Booking\Enum\BookingStatus;
use PHPUnit\Framework\TestCase;

final class BookingTest extends TestCase
{
    public function testBusinessDefaults(): void
    {
        $booking = new Booking();

        self::assertSame(BookingStatus::Pending, $booking->getStatus());
        self::assertSame('0.00', $booking->getTotalPrice());
        self::assertSame('EUR', $booking->getCurrency());
        self::assertCount(0, $booking->getItems());
    }

    public function testGetMarkingReflectsStatus(): void
    {
        $booking = new Booking();
        self::assertSame('pending', $booking->getMarking());

        $booking->setMarking('confirmed');
        self::assertSame(BookingStatus::Confirmed, $booking->getStatus());
    }

    public function testSetMarkingWithUnknownPlaceThrows(): void
    {
        $this->expectException(\ValueError::class);

        (new Booking())->setMarking('not-a-real-place');
    }

    public function testAddItemLinksBothSidesAndAvoidsDuplicates(): void
    {
        $booking = new Booking();
        $item = new BookingItem();

        $booking->addItem($item);
        $booking->addItem($item); // ajout en double : ignoré

        self::assertCount(1, $booking->getItems());
        self::assertSame($booking, $item->getBooking());
    }

    public function testRemoveItemDetaches(): void
    {
        $booking = new Booking();
        $item = new BookingItem();
        $booking->addItem($item);

        $booking->removeItem($item);

        self::assertCount(0, $booking->getItems());
        self::assertNull($item->getBooking());
    }
}
