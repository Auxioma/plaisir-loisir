<?php

declare(strict_types=1);

namespace App\Tests\Availability\Service;

use App\Availability\Entity\Availability;
use App\Availability\Service\AvailabilityService;
use App\Catalog\Entity\Service;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class AvailabilityServiceTest extends TestCase
{
    public function testCreateSlotPersists(): void
    {
        $service = new Service();
        $start = new \DateTimeImmutable('2026-07-01 10:00');
        $end = new \DateTimeImmutable('2026-07-01 12:00');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(Availability::class));
        $em->expects(self::once())->method('flush');

        $slot = (new AvailabilityService($em))->createSlot($service, $start, $end, 8);

        self::assertSame($service, $slot->getService());
        self::assertSame(8, $slot->getCapacity());
        self::assertSame($start, $slot->getStartsAt());
    }

    public function testCreateSlotRejectsEndBeforeStart(): void
    {
        $start = new \DateTimeImmutable('2026-07-01 12:00');
        $end = new \DateTimeImmutable('2026-07-01 10:00');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);

        (new AvailabilityService($em))->createSlot(new Service(), $start, $end, 8);
    }

    public function testCreateSlotRejectsInvalidCapacity(): void
    {
        $start = new \DateTimeImmutable('2026-07-01 10:00');
        $end = new \DateTimeImmutable('2026-07-01 12:00');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);

        (new AvailabilityService($em))->createSlot(new Service(), $start, $end, 0);
    }

    public function testReserveFlushes(): void
    {
        $slot = (new Availability())->setCapacity(5);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        (new AvailabilityService($em))->reserve($slot, 2);

        self::assertSame(2, $slot->getBooked());
    }
}
