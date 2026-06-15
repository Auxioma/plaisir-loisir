<?php

declare(strict_types=1);

namespace App\Catalog\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Catalog\Entity\Service;
use App\Catalog\State\ServiceCollectionProvider;
use App\Catalog\State\ServiceItemProvider;

/**
 * Représentation publique d'une activité (prestation).
 */
#[ApiResource(
    shortName: 'Activity',
    operations: [
        new GetCollection(
            uriTemplate: '/activities',
            provider: ServiceCollectionProvider::class,
            description: 'Liste des activités publiées.',
        ),
        new Get(
            uriTemplate: '/activities/{id}',
            provider: ServiceItemProvider::class,
            description: 'Détail d\'une activité.',
        ),
    ],
)]
final class ServiceResource
{
    public ?string $id = null;
    public string $title = '';
    public string $slug = '';
    public ?string $shortDescription = null;
    public string $description = '';
    public string $status = '';
    public string $bookingType = '';
    public string $currency = '';
    public ?int $durationMinutes = null;
    public ?int $capacity = null;
    public ?string $level = null;

    /**
     * @var list<string>
     */
    public array $languages = [];

    public ?string $included = null;
    public string $cancellationPolicy = '';
    public ?string $city = null;
    public ?string $country = null;
    public ?string $providerName = null;
    public ?string $categoryName = null;
    public ?string $destinationName = null;

    /**
     * @var list<array<string, mixed>>
     */
    public array $packages = [];

    /**
     * @var list<array<string, mixed>>
     */
    public array $media = [];

    public static function fromEntity(Service $service): self
    {
        $resource = new self();
        $resource->id = (string) $service->getId();
        $resource->title = $service->getTitle();
        $resource->slug = $service->getSlug();
        $resource->shortDescription = $service->getShortDescription();
        $resource->description = $service->getDescription();
        $resource->status = $service->getStatus()->value;
        $resource->bookingType = $service->getBookingType()->value;
        $resource->currency = $service->getCurrency();
        $resource->durationMinutes = $service->getDurationMinutes();
        $resource->capacity = $service->getCapacity();
        $resource->level = $service->getLevel()?->value;
        $resource->languages = $service->getLanguages();
        $resource->included = $service->getIncluded();
        $resource->cancellationPolicy = $service->getCancellationPolicy()->value;
        $resource->city = $service->getCity();
        $resource->country = $service->getCountry();
        $resource->providerName = $service->getProvider()?->getDisplayName();
        $resource->categoryName = $service->getCategory()?->getName();
        $resource->destinationName = $service->getDestination()?->getName();

        foreach ($service->getPackages() as $package) {
            $resource->packages[] = [
                'name' => $package->getName(),
                'price' => $package->getPrice(),
                'currency' => $package->getCurrency(),
                'pricingUnit' => $package->getPricingUnit()->value,
                'deliveryDays' => $package->getDeliveryDays(),
            ];
        }

        foreach ($service->getMedia() as $media) {
            $resource->media[] = [
                'path' => $media->getPath(),
                'type' => $media->getType(),
                'position' => $media->getPosition(),
            ];
        }

        return $resource;
    }
}
