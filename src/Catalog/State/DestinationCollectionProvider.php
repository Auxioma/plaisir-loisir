<?php

declare(strict_types=1);

namespace App\Catalog\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\ApiResource\DestinationResource;
use App\Catalog\Entity\Destination;
use App\Catalog\Repository\DestinationRepository;

/**
 * @implements ProviderInterface<DestinationResource>
 */
final class DestinationCollectionProvider implements ProviderInterface
{
    public function __construct(private readonly DestinationRepository $destinations)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return DestinationResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return array_map(
            static fn (Destination $destination): DestinationResource => DestinationResource::fromEntity($destination),
            $this->destinations->findBy([], ['name' => 'ASC']),
        );
    }
}
