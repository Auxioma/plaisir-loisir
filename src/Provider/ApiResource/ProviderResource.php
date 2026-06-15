<?php

declare(strict_types=1);

namespace App\Provider\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Provider\Dto\BecomeProviderInput;
use App\Provider\Entity\ProviderProfile;
use App\Provider\State\BecomeProviderProcessor;

/**
 * Représentation publique d'un profil prestataire.
 */
#[ApiResource(
    shortName: 'ProviderProfile',
    operations: [
        new Post(
            uriTemplate: '/provider-profiles',
            input: BecomeProviderInput::class,
            processor: BecomeProviderProcessor::class,
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            description: 'Devenir prestataire : crée le profil et le soumet à vérification.',
        ),
    ],
)]
final class ProviderResource
{
    public ?string $id = null;
    public string $displayName = '';
    public ?string $companyName = null;
    public ?string $bio = null;
    public string $status = '';

    public static function fromEntity(ProviderProfile $profile): self
    {
        $resource = new self();
        $resource->id = (string) $profile->getId();
        $resource->displayName = $profile->getDisplayName();
        $resource->companyName = $profile->getCompanyName();
        $resource->bio = $profile->getBio();
        $resource->status = $profile->getStatus()->value;

        return $resource;
    }
}
