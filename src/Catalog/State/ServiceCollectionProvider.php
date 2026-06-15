<?php

declare(strict_types=1);

namespace App\Catalog\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\ApiResource\ServiceResource;
use App\Catalog\Entity\Service;
use App\Catalog\Enum\ServiceStatus;
use App\Catalog\Repository\ServiceRepository;

/**
 * @implements ProviderInterface<ServiceResource>
 */
final class ServiceCollectionProvider implements ProviderInterface
{
    public function __construct(private readonly ServiceRepository $services)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return ServiceResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return array_map(
            static fn (Service $service): ServiceResource => ServiceResource::fromEntity($service),
            $this->services->findBy(['status' => ServiceStatus::Published], ['createdAt' => 'DESC']),
        );
    }
}
