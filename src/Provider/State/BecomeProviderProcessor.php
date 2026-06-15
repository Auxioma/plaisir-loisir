<?php

declare(strict_types=1);

namespace App\Provider\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Provider\ApiResource\ProviderResource;
use App\Provider\Dto\BecomeProviderInput;
use App\Provider\Service\ProviderOnboardingService;
use App\User\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Pont API Platform -> service d'onboarding prestataire.
 *
 * @implements ProcessorInterface<BecomeProviderInput, ProviderResource>
 */
final class BecomeProviderProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ProviderOnboardingService $onboardingService,
        private readonly Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProviderResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Authentification requise.');
        }

        return ProviderResource::fromEntity($this->onboardingService->becomeProvider($user, $data));
    }
}
