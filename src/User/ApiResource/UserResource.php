<?php

declare(strict_types=1);

namespace App\User\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\User\Dto\RegisterUserInput;
use App\User\Entity\User;
use App\User\State\MeProvider;
use App\User\State\RegisterUserProcessor;

/**
 * Représentation publique d'un utilisateur exposée par l'API (jamais l'entité directement).
 */
#[ApiResource(
    shortName: 'User',
    operations: [
        new Post(
            uriTemplate: '/users',
            input: RegisterUserInput::class,
            processor: RegisterUserProcessor::class,
            description: 'Inscription d\'un nouvel utilisateur.',
        ),
        new Get(
            uriTemplate: '/me',
            provider: MeProvider::class,
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            description: 'Utilisateur actuellement authentifié.',
        ),
    ],
)]
final class UserResource
{
    public ?string $id = null;
    public string $email = '';
    public string $firstName = '';
    public string $lastName = '';
    public ?string $phone = null;
    public string $status = '';

    public static function fromEntity(User $user): self
    {
        $resource = new self();
        $resource->id = (string) $user->getId();
        $resource->email = $user->getEmail();
        $resource->firstName = $user->getFirstName();
        $resource->lastName = $user->getLastName();
        $resource->phone = $user->getPhone();
        $resource->status = $user->getStatus()->value;

        return $resource;
    }
}
