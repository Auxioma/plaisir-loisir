<?php

declare(strict_types=1);

namespace App\Search;

use App\Catalog\Entity\Destination;
use App\Catalog\Entity\Service;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Indexe les entités du catalogue dans Elasticsearch (PostgreSQL reste la source
 * de vérité ; l'index est un reflet reconstructible).
 */
final class CatalogIndexer
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $elasticsearchUrl,
    ) {
    }

    /**
     * (Re)crée les index avec un mapping explicite (les champs filtrés sont en keyword).
     */
    public function createIndices(): void
    {
        $this->recreateIndex('activities', [
            'title' => ['type' => 'text'],
            'description' => ['type' => 'text'],
            'city' => ['type' => 'text'],
            'status' => ['type' => 'keyword'],
            'categoryId' => ['type' => 'keyword'],
            'destinationId' => ['type' => 'keyword'],
        ]);

        $this->recreateIndex('destinations', [
            'name' => ['type' => 'text'],
            'country' => ['type' => 'text'],
        ]);
    }

    public function indexService(Service $service): void
    {
        $this->index('activities', (string) $service->getId(), [
            'title' => $service->getTitle(),
            'description' => $service->getDescription(),
            'city' => $service->getCity(),
            'status' => $service->getStatus()->value,
            'categoryId' => $this->idString($service->getCategory()?->getId()),
            'destinationId' => $this->idString($service->getDestination()?->getId()),
        ]);
    }

    public function indexDestination(Destination $destination): void
    {
        $this->index('destinations', (string) $destination->getId(), [
            'name' => $destination->getName(),
            'country' => $destination->getCountry(),
        ]);
    }

    public function removeService(string $id): void
    {
        $this->send('DELETE', '/activities/_doc/'.$id);
    }

    /**
     * @param array<string, array<string, string>> $properties
     */
    private function recreateIndex(string $index, array $properties): void
    {
        $this->send('DELETE', '/'.$index);
        $this->send('PUT', '/'.$index, ['json' => ['mappings' => ['properties' => $properties]]]);
    }

    /**
     * @param array<string, mixed> $document
     */
    private function index(string $index, string $id, array $document): void
    {
        $this->send('PUT', '/'.$index.'/_doc/'.$id, ['json' => $document]);
    }

    private function idString(?Ulid $id): ?string
    {
        return null === $id ? null : (string) $id;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function send(string $method, string $path, array $options = []): void
    {
        // getStatusCode() force l'exécution (HttpClient est paresseux) sans lever
        // d'exception sur un code 4xx (ex. DELETE d'un index inexistant).
        $this->client->request($method, rtrim($this->elasticsearchUrl, '/').$path, $options)->getStatusCode();
    }
}
