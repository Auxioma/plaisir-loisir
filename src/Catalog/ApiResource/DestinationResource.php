<?php

declare(strict_types=1);

namespace App\Catalog\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Catalog\Entity\Destination;
use App\Catalog\State\DestinationCollectionProvider;

/**
 * Représentation publique d'une destination.
 */
#[ApiResource(
    shortName: 'Destination',
    operations: [
        new GetCollection(
            uriTemplate: '/destinations',
            provider: DestinationCollectionProvider::class,
            description: 'Liste des destinations.',
        ),
    ],
)]
final class DestinationResource
{
    public ?string $id = null;
    public string $name = '';
    public string $slug = '';
    public string $country = '';
    public ?string $region = null;
    public ?string $description = null;
    public ?string $heroImage = null;

    public static function fromEntity(Destination $destination): self
    {
        $resource = new self();
        $resource->id = (string) $destination->getId();
        $resource->name = $destination->getName();
        $resource->slug = $destination->getSlug();
        $resource->country = $destination->getCountry();
        $resource->region = $destination->getRegion();
        $resource->description = $destination->getDescription();
        $resource->heroImage = $destination->getHeroImage();

        return $resource;
    }
}
