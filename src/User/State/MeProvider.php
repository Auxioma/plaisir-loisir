<?php

declare(strict_types=1);

namespace App\User\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\User\ApiResource\UserResource;
use App\User\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Fournit la ressource correspondant à l'utilisateur authentifié (endpoint /api/me).
 *
 * @implements ProviderInterface<UserResource>
 */
final class MeProvider implements ProviderInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?UserResource
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return null;
        }

        return UserResource::fromEntity($user);
    }
}
