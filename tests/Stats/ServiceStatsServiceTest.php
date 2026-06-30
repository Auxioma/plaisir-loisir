<?php

declare(strict_types=1);

namespace App\Tests\Stats;

use App\Booking\Repository\BookingRepository;
use App\Catalog\Entity\Service;
use App\Review\Repository\ReviewRepository;
use App\Stats\ServiceStatsService;
use PHPUnit\Framework\TestCase;

final class ServiceStatsServiceTest extends TestCase
{
    public function testForServiceAssemblesStatsFromRepositories(): void
    {
        $service = new Service();

        $reviews = $this->createStub(ReviewRepository::class);
        $reviews->method('countPublishedForService')->willReturn(12);
        $reviews->method('averageRatingForService')->willReturn(4.5);

        $bookings = $this->createStub(BookingRepository::class);
        $bookings->method('countForService')->willReturn(30);

        $stats = (new ServiceStatsService($reviews, $bookings))->forService($service);

        self::assertSame(12, $stats->reviewsCount);
        self::assertSame(4.5, $stats->averageRating);
        self::assertSame(30, $stats->bookingsCount);
    }

    public function testForServiceWithoutReviewsHasNullAverage(): void
    {
        $reviews = $this->createStub(ReviewRepository::class);
        $reviews->method('countPublishedForService')->willReturn(0);
        $reviews->method('averageRatingForService')->willReturn(null);

        $bookings = $this->createStub(BookingRepository::class);
        $bookings->method('countForService')->willReturn(0);

        $stats = (new ServiceStatsService($reviews, $bookings))->forService(new Service());

        self::assertSame(0, $stats->reviewsCount);
        self::assertNull($stats->averageRating);
    }
}
