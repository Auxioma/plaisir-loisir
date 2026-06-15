<?php

declare(strict_types=1);

namespace App\Tests\Booking\Service;

use App\Booking\Entity\Booking;
use App\Booking\Entity\BookingItem;
use App\Booking\Enum\BookingStatus;
use App\Booking\Service\BookingService;
use App\Catalog\Entity\Service;
use App\Catalog\Entity\ServicePackage;
use App\Catalog\Enum\ServiceStatus;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class BookingServiceTest extends TestCase
{
    private function publishedService(): Service
    {
        return (new Service())->setStatus(ServiceStatus::Published);
    }

    private function packageFor(Service $service): ServicePackage
    {
        return (new ServicePackage())
            ->setName('Formule Standard')
            ->setPrice('49.90')
            ->setCurrency('EUR')
            ->setService($service);
    }

    public function testCreateBookingSnapshotsPackageAndComputesTotal(): void
    {
        $client = new User();
        $service = $this->publishedService();
        $package = $this->packageFor($service);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(Booking::class));
        $em->expects(self::once())->method('flush');

        $booking = (new BookingService($em))->createBooking($client, $service, $package, 3);

        self::assertSame($client, $booking->getClient());
        self::assertSame($service, $booking->getService());
        self::assertSame(BookingStatus::Pending, $booking->getStatus());
        self::assertSame('EUR', $booking->getCurrency());
        // 49.90 x 3, calculé sans float : 149.70.
        self::assertSame('149.70', $booking->getTotalPrice());

        self::assertCount(1, $booking->getItems());
        $item = $booking->getItems()->first();
        self::assertInstanceOf(BookingItem::class, $item);
        self::assertSame('Formule Standard', $item->getLabel());
        self::assertSame('49.90', $item->getUnitPrice());
        self::assertSame(3, $item->getQuantity());
        self::assertSame($booking, $item->getBooking());
    }

    public function testCreateBookingRejectsQuantityBelowOne(): void
    {
        $service = $this->publishedService();
        $package = $this->packageFor($service);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);

        (new BookingService($em))->createBooking(new User(), $service, $package, 0);
    }

    public function testCreateBookingRejectsPackageFromAnotherService(): void
    {
        $service = $this->publishedService();
        $package = $this->packageFor($this->publishedService()); // appartient à une autre activité

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);

        (new BookingService($em))->createBooking(new User(), $service, $package, 1);
    }

    public function testCreateBookingRejectsUnpublishedService(): void
    {
        $service = new Service(); // statut Draft par défaut
        $package = $this->packageFor($service);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);

        (new BookingService($em))->createBooking(new User(), $service, $package, 1);
    }
}
