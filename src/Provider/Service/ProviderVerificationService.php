<?php

declare(strict_types=1);

namespace App\Provider\Service;

use App\Provider\Entity\ProviderProfile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\Registry;

/**
 * Validation d'un annonceur par l'administration (transitions du workflow
 * provider_verification : approve / reject).
 */
final class ProviderVerificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Registry $workflowRegistry,
    ) {
    }

    public function approve(ProviderProfile $provider): void
    {
        $this->apply($provider, 'approve');
    }

    public function reject(ProviderProfile $provider): void
    {
        $this->apply($provider, 'reject');
    }

    /**
     * @throws \InvalidArgumentException si la transition n'est pas possible depuis l'état courant
     */
    private function apply(ProviderProfile $provider, string $transition): void
    {
        $workflow = $this->workflowRegistry->get($provider, 'provider_verification');
        if (!$workflow->can($provider, $transition)) {
            throw new \InvalidArgumentException(\sprintf('Transition « %s » impossible depuis l\'état actuel.', $transition));
        }

        $workflow->apply($provider, $transition);
        $this->entityManager->flush();
    }
}
