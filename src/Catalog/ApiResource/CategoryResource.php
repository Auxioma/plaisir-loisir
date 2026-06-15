<?php

declare(strict_types=1);

namespace App\Catalog\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Catalog\Entity\Category;
use App\Catalog\State\CategoryCollectionProvider;

/**
 * Représentation publique d'une catégorie.
 */
#[ApiResource(
    shortName: 'Category',
    operations: [
        new GetCollection(
            uriTemplate: '/categories',
            provider: CategoryCollectionProvider::class,
            description: 'Liste des catégories.',
        ),
    ],
)]
final class CategoryResource
{
    public ?string $id = null;
    public string $name = '';
    public string $slug = '';
    public ?string $parentId = null;
    public int $position = 0;

    public static function fromEntity(Category $category): self
    {
        $resource = new self();
        $resource->id = (string) $category->getId();
        $resource->name = $category->getName();
        $resource->slug = $category->getSlug();
        $resource->parentId = $category->getParent() ? (string) $category->getParent()->getId() : null;
        $resource->position = $category->getPosition();

        return $resource;
    }
}
