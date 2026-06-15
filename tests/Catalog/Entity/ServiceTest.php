<?php

declare(strict_types=1);

namespace App\Tests\Catalog\Entity;

use App\Catalog\Entity\Media;
use App\Catalog\Entity\Service;
use App\Catalog\Entity\ServicePackage;
use App\Catalog\Enum\BookingType;
use App\Catalog\Enum\CancellationPolicy;
use App\Catalog\Enum\ServiceStatus;
use PHPUnit\Framework\TestCase;

final class ServiceTest extends TestCase
{
    public function testBusinessDefaults(): void
    {
        $service = new Service();

        self::assertSame(BookingType::ServiceProduct, $service->getBookingType());
        self::assertSame(ServiceStatus::Draft, $service->getStatus());
        self::assertSame('EUR', $service->getCurrency());
        self::assertSame(CancellationPolicy::Flexible, $service->getCancellationPolicy());
        self::assertSame([], $service->getLanguages());
        self::assertCount(0, $service->getPackages());
        self::assertCount(0, $service->getMedia());
    }

    public function testAddPackageLinksBothSidesAndAvoidsDuplicates(): void
    {
        $service = new Service();
        $package = new ServicePackage();

        $service->addPackage($package);
        $service->addPackage($package); // ajout en double : ignoré

        self::assertCount(1, $service->getPackages());
        self::assertSame($service, $package->getService());
    }

    public function testAddMediaLinksBothSides(): void
    {
        $service = new Service();
        $media = new Media();

        $service->addMedia($media);

        self::assertCount(1, $service->getMedia());
        self::assertSame($service, $media->getService());
    }
}
