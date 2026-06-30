<?php

declare(strict_types=1);

namespace App\Tests\Search;

use App\Catalog\Entity\Service;
use App\Catalog\Repository\DestinationRepository;
use App\Catalog\Repository\ServiceRepository;
use App\Search\ElasticsearchSearch;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ElasticsearchSearchTest extends TestCase
{
    public function testSearchActivitiesQueriesElasticsearchThenLoadsEntities(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['hits' => ['hits' => [['_id' => 'id1'], ['_id' => 'id2']]]]);

        $client = $this->createMock(HttpClientInterface::class);
        $client->expects(self::once())->method('request')
            ->with('GET', self::stringContains('/activities/_search'), self::anything())
            ->willReturn($response);

        $expected = [new Service()];
        $services = $this->createMock(ServiceRepository::class);
        $services->expects(self::once())->method('findByIds')->with(['id1', 'id2'])->willReturn($expected);

        $search = new ElasticsearchSearch($client, $services, $this->createStub(DestinationRepository::class), 'http://es:9200');

        self::assertSame($expected, $search->searchActivities('kayak'));
    }

    public function testSearchDestinationsReturnsEmptyWhenNoHits(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['hits' => ['hits' => []]]);

        $client = $this->createStub(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $destinations = $this->createMock(DestinationRepository::class);
        $destinations->expects(self::once())->method('findByIds')->with([])->willReturn([]);

        $search = new ElasticsearchSearch($client, $this->createStub(ServiceRepository::class), $destinations, 'http://es:9200');

        self::assertSame([], $search->searchDestinations('lyon'));
    }
}
