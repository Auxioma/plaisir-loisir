<?php

declare(strict_types=1);

namespace App\Provider\Service;

use App\Provider\Dto\BecomeProviderInput;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Repository\ProviderProfileRepository;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Workflow\Registry;

/**
 * Logique métier de l'accès au statut prestataire.
 */
final class ProviderOnboardingService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProviderProfileRepository $providerProfiles,
        private readonly Registry $workflowRegistry,
    ) {
    }

    public function becomeProvider(User $user, BecomeProviderInput $input): ProviderProfile
    {
        if (null !== $this->providerProfiles->findOneByUser($user)) {
            throw new ConflictHttpException('Cet utilisateur est déjà prestataire.');
        }

        $profile = new ProviderProfile();
        $profile->setUser($user);
        $profile->setDisplayName($input->displayName);
        $profile->setCompanyName($input->companyName);
        $profile->setBio($input->bio);

        // Statut initial "draft" (défaut), puis soumission à vérification via le workflow.
        $this->workflowRegistry->get($profile, 'provider_verification')->apply($profile, 'submit');

        $this->entityManager->persist($profile);
        $this->entityManager->flush();

        return $profile;
    }
}
