<?php

declare(strict_types=1);

namespace App\Tests\Booking\Entity;

use App\Booking\Entity\BookingItem;
use PHPUnit\Framework\TestCase;

final class BookingItemTest extends TestCase
{
    public function testDefaults(): void
    {
        $item = new BookingItem();

        self::assertSame(1, $item->getQuantity());
        self::assertSame('EUR', $item->getCurrency());
        self::assertNull($item->getServicePackage());
        self::assertNull($item->getBooking());
    }

    public function testSnapshotFieldsAreStored(): void
    {
        $item = (new BookingItem())
            ->setLabel('Formule Standard')
            ->setUnitPrice('49.90')
            ->setQuantity(3);

        self::assertSame('Formule Standard', $item->getLabel());
        self::assertSame('49.90', $item->getUnitPrice());
        self::assertSame(3, $item->getQuantity());
    }
}
