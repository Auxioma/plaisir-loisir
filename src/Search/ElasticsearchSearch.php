<?php

declare(strict_types=1);

namespace App\Search;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\Destination;
use App\Catalog\Repository\DestinationRepository;
use App\Catalog\Repository\ServiceRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Implémentation de la recherche via Elasticsearch. Elasticsearch ne renvoie que
 * des identifiants (par pertinence) ; les entités sont ensuite chargées depuis
 * PostgreSQL, qui reste la source de vérité.
 */
final class ElasticsearchSearch implements SearchService
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly ServiceRepository $services,
        private readonly DestinationRepository $destinations,
        private readonly string $elasticsearchUrl,
    ) {
    }

    public function searchActivities(string $query, ?Category $category = null, ?Destination $destination = null, int $limit = 20): array
    {
        $filter = [['term' => ['status' => 'published']]];
        if (null !== $category && null !== $category->getId()) {
            $filter[] = ['term' => ['categoryId' => (string) $category->getId()]];
        }
        if (null !== $destination && null !== $destination->getId()) {
            $filter[] = ['term' => ['destinationId' => (string) $destination->getId()]];
        }

        $ids = $this->searchIds('activities', $limit, [
            'must' => $this->keywords($query, ['title', 'description', 'city']),
            'filter' => $filter,
        ]);

        return $this->services->findByIds($ids);
    }

    public function searchDestinations(string $query, int $limit = 20): array
    {
        $ids = $this->searchIds('destinations', $limit, [
            'must' => $this->keywords($query, ['name', 'country']),
        ]);

        return $this->destinations->findByIds($ids);
    }

    /**
     * @param list<string> $fields
     *
     * @return list<array<string, mixed>>
     */
    private function keywords(string $query, array $fields): array
    {
        $query = trim($query);
        if ('' === $query) {
            return [['match_all' => (object) []]];
        }

        return [['multi_match' => ['query' => $query, 'fields' => $fields]]];
    }

    /**
     * @param array<string, mixed> $bool
     *
     * @return string[]
     */
    private function searchIds(string $index, int $limit, array $bool): array
    {
        $response = $this->client->request('GET', rtrim($this->elasticsearchUrl, '/').'/'.$index.'/_search', [
            'json' => ['size' => $limit, 'query' => ['bool' => $bool]],
        ]);

        /** @var array{hits?: array{hits?: list<array{_id: string}>}} $data */
        $data = $response->toArray(false);

        return array_map(static fn (array $hit): string => $hit['_id'], $data['hits']['hits'] ?? []);
    }
}
