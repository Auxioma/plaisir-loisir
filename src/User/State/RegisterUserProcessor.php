<?php

declare(strict_types=1);

namespace App\User\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\User\ApiResource\UserResource;
use App\User\Dto\RegisterUserInput;
use App\User\Service\RegistrationService;

/**
 * Pont API Platform → service métier pour l'inscription.
 *
 * @implements ProcessorInterface<RegisterUserInput, UserResource>
 */
final class RegisterUserProcessor implements ProcessorInterface
{
    public function __construct(private readonly RegistrationService $registrationService)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserResource
    {
        // $data est garanti de type RegisterUserInput par le contrat ProcessorInterface (voir @implements).
        return UserResource::fromEntity($this->registrationService->register($data));
    }
}
