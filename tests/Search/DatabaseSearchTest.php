<?php

declare(strict_types=1);

namespace App\Tests\Search;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\Destination;
use App\Catalog\Entity\Service;
use App\Catalog\Repository\DestinationRepository;
use App\Catalog\Repository\ServiceRepository;
use App\Search\DatabaseSearch;
use PHPUnit\Framework\TestCase;

final class DatabaseSearchTest extends TestCase
{
    public function testSearchActivitiesDelegatesToRepositoryWithFilters(): void
    {
        $category = new Category();
        $expected = [new Service()];

        $services = $this->createMock(ServiceRepository::class);
        $services->expects(self::once())
            ->method('searchPublished')
            ->with('kayak', $category, null, 10)
            ->willReturn($expected);

        $search = new DatabaseSearch($services, $this->createStub(DestinationRepository::class));

        self::assertSame($expected, $search->searchActivities('kayak', $category, null, 10));
    }

    public function testSearchDestinationsDelegatesToRepository(): void
    {
        $expected = [new Destination()];

        $destinations = $this->createMock(DestinationRepository::class);
        $destinations->expects(self::once())
            ->method('searchByName')
            ->with('lyon', 20)
            ->willReturn($expected);

        $search = new DatabaseSearch($this->createStub(ServiceRepository::class), $destinations);

        self::assertSame($expected, $search->searchDestinations('lyon'));
    }
}
