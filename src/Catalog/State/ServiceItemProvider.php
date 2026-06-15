<?php

declare(strict_types=1);

namespace App\Catalog\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\ApiResource\ServiceResource;
use App\Catalog\Repository\ServiceRepository;
use Symfony\Component\Uid\Ulid;

/**
 * @implements ProviderInterface<ServiceResource>
 */
final class ServiceItemProvider implements ProviderInterface
{
    public function __construct(private readonly ServiceRepository $services)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?ServiceResource
    {
        $id = $uriVariables['id'] ?? null;

        if (!\is_string($id) || !Ulid::isValid($id)) {
            return null;
        }

        $service = $this->services->find(Ulid::fromString($id));

        return null !== $service ? ServiceResource::fromEntity($service) : null;
    }
}
