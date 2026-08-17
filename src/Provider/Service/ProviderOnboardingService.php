<?php

declare(strict_types=1);

namespace App\Provider\Service;

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

    public function becomeProvider(User $user, string $displayName, ?string $companyName, ?string $bio): ProviderProfile
    {
        if (null !== $this->providerProfiles->findOneByUser($user)) {
            throw new ConflictHttpException('Cet utilisateur est déjà prestataire.');
        }

        $profile = new ProviderProfile();
        $profile->setUser($user);
        $profile->setDisplayName($displayName);
        $profile->setCompanyName($companyName);
        $profile->setBio($bio);

        // Statut initial "draft" (défaut), puis soumission à vérification via le workflow.
        $this->workflowRegistry->get($profile, 'provider_verification')->apply($profile, 'submit');

        $this->entityManager->persist($profile);
        $this->entityManager->flush();

        return $profile;
    }

    /**
     * Ouvre un dossier prestataire VIDE, laissé en brouillon.
     *
     * Appelé à l'inscription, quand le visiteur est passé par la tuile « Pro
     * Prestataire » de l'écran de choix. À ce moment on ne connaît que son nom :
     * ni raison sociale, ni statut juridique, ni présentation. Le dossier reste
     * donc en « draft » et n'est PAS soumis à vérification — contrairement à
     * becomeProvider(), qui suppose un dossier déjà renseigné.
     *
     * C'est le futur espace professionnel qui complétera ces informations puis
     * déclenchera la transition « submit ».
     */
    public function startDraftProfile(User $user, string $displayName): ProviderProfile
    {
        if (null !== $this->providerProfiles->findOneByUser($user)) {
            throw new ConflictHttpException('Cet utilisateur est déjà prestataire.');
        }

        $profile = new ProviderProfile();
        $profile->setUser($user);
        $profile->setDisplayName($displayName);

        $this->entityManager->persist($profile);
        $this->entityManager->flush();

        return $profile;
    }
}
