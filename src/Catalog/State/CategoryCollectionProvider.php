<?php

declare(strict_types=1);

namespace App\Catalog\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\ApiResource\CategoryResource;
use App\Catalog\Entity\Category;
use App\Catalog\Repository\CategoryRepository;

/**
 * @implements ProviderInterface<CategoryResource>
 */
final class CategoryCollectionProvider implements ProviderInterface
{
    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return CategoryResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return array_map(
            static fn (Category $category): CategoryResource => CategoryResource::fromEntity($category),
            $this->categories->findBy([], ['position' => 'ASC']),
        );
    }
}
