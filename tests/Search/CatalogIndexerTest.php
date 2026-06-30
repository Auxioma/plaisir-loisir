<?php

declare(strict_types=1);

namespace App\Tests\Search;

use App\Catalog\Entity\Service;
use App\Catalog\Enum\ServiceStatus;
use App\Search\CatalogIndexer;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class CatalogIndexerTest extends TestCase
{
    public function testIndexServiceSendsDocumentToElasticsearch(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects(self::once())->method('request')->with(
            'PUT',
            self::stringContains('/activities/_doc/'),
            self::callback(static function (array $options): bool {
                $document = $options['json'] ?? [];

                return 'Sortie kayak' === ($document['title'] ?? null)
                    && 'published' === ($document['status'] ?? null);
            }),
        )->willReturn($this->createStub(ResponseInterface::class));

        $service = (new Service())
            ->setTitle('Sortie kayak')
            ->setDescription('Descente en kayak encadrée')
            ->setStatus(ServiceStatus::Published);

        (new CatalogIndexer($client, 'http://es:9200'))->indexService($service);
    }
}
