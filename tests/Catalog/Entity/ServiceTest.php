<?php

declare(strict_types=1);

namespace App\Tests\Catalog\Entity;

use App\Catalog\Entity\Media;
use App\Catalog\Entity\Service;
use App\Catalog\Entity\ServicePackage;
use App\Catalog\Enum\ActivityType;
use App\Catalog\Enum\BookingType;
use App\Catalog\Enum\CancellationPolicy;
use App\Catalog\Enum\OpeningPeriod;
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

        // Champs additionnels (maquette) : non renseignés par défaut.
        self::assertNull($service->getSubtitle());
        self::assertNull($service->getActivityType());
        self::assertNull($service->getMinimumAge());
        self::assertNull($service->getProgramme());
        self::assertNull($service->getMeetingPoint());
        self::assertNull($service->getOpeningPeriod());
        self::assertNull($service->getAudience());
    }

    public function testActivityTypeAndOpeningPeriodAreAssignable(): void
    {
        $service = (new Service())
            ->setActivityType(ActivityType::GuidedTour)
            ->setOpeningPeriod(OpeningPeriod::SpringSummer)
            ->setMinimumAge(12);

        self::assertSame(ActivityType::GuidedTour, $service->getActivityType());
        self::assertSame(OpeningPeriod::SpringSummer, $service->getOpeningPeriod());
        self::assertSame(12, $service->getMinimumAge());
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
