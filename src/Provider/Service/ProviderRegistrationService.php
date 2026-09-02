<?php

declare(strict_types=1);

namespace App\Provider\Service;

use App\Catalog\Entity\Category;
use App\Legal\Entity\CompanyIdentity;
use App\Legal\Service\ConsentService;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Repository\ProviderProfileRepository;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use App\User\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Workflow\Registry;

/**
 * Inscription d'un professionnel — étape 1 puis étape 2 de la maquette.
 *
 * POURQUOI UN SERVICE À PART DE RegistrationService
 * L'inscription client tient en un écran et quatre champs ; l'inscription
 * professionnelle en compte deux, crée trois objets (compte, dossier
 * prestataire, identité légale) et se termine par une transition de workflow.
 * Ce sont deux parcours métier distincts, pas deux variantes d'un même
 * formulaire — la maquette elle-même les dessine séparément. Les faire tenir
 * dans une seule méthode aurait donné une signature à huit paramètres dont la
 * moitié n'auraient jamais servi côté client.
 *
 * DEUX ÉTAPES, DEUX ÉCRITURES
 * Le compte est créé DÈS L'ÉTAPE 1, et non à la fin du parcours. Deux raisons :
 * les pièces de l'étape 2 doivent se rattacher à un dossier qui existe, et le
 * mot de passe est haché tout de suite au lieu de séjourner en clair dans la
 * session le temps du téléversement. Un dossier abandonné entre les deux
 * étapes reste donc en « draft » : il n'est soumis à personne et le
 * prestataire peut reprendre là où il s'était arrêté.
 */
final class ProviderRegistrationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository,
        private readonly ProviderProfileRepository $providerProfiles,
        private readonly ConsentService $consentService,
        private readonly RequestStack $requestStack,
        private readonly Registry $workflowRegistry,
    ) {
    }

    /**
     * Étape 1/2 — le compte, le dossier et l'identité légale naissent ensemble.
     *
     * @throws ConflictHttpException si un compte existe déjà avec cet e-mail
     */
    public function registerFirstStep(
        string $lastName,
        string $firstName,
        string $email,
        string $plainPassword,
        ?string $phone,
        ?Category $mainCategory,
        ?string $registeredOffice,
    ): ProviderProfile {
        // Même normalisation que l'inscription client : sans elle
        // « Jean@x.fr » et « jean@x.fr » ouvriraient deux comptes.
        $email = mb_strtolower(trim($email));

        if (null !== $this->userRepository->findOneBy(['email' => $email])) {
            throw new ConflictHttpException('Un compte existe déjà avec cet e-mail.');
        }

        $phone = null !== $phone ? trim($phone) : null;

        $user = (new User())
            ->setEmail($email)
            ->setFirstName(mb_substr(trim($firstName), 0, 100))
            ->setLastName(mb_substr(trim($lastName), 0, 100))
            ->setPhone('' !== $phone ? $phone : null)
            ->setStatus(UserStatus::Active);

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        // getRoles() ajoute toujours ROLE_USER : un prestataire reste un
        // utilisateur de la plateforme, il gagne seulement un rôle en plus.
        $user->setRoles(['ROLE_PROVIDER']);

        $this->entityManager->persist($user);
        // Le dossier référence l'utilisateur : celui-ci doit exister d'abord.
        $this->entityManager->flush();

        $displayName = trim($firstName.' '.$lastName);

        $profile = (new ProviderProfile())
            ->setUser($user)
            ->setDisplayName('' !== $displayName ? mb_substr($displayName, 0, 120) : $email)
            ->setMainCategory($mainCategory);

        $this->entityManager->persist($profile);

        // L'adresse du siège social appartient à l'identité légale, pas au
        // profil public : c'est une donnée que l'administration vérifie, pas
        // une donnée que les clients consultent.
        if (null !== $registeredOffice && '' !== trim($registeredOffice)) {
            $identity = (new CompanyIdentity())
                ->setProviderProfile($profile)
                ->setRegisteredStreet(mb_substr(trim($registeredOffice), 0, 255));

            $this->entityManager->persist($identity);
        }

        $this->entityManager->flush();

        // La maquette ne comporte pas de case à cocher, mais l'article 7.1 du
        // RGPD exige de pouvoir prouver l'acceptation. L'écran porte donc la
        // mention « en créant votre compte, vous acceptez… » sous le bouton, et
        // l'acceptation est consignée ici, avec la version, la date, l'adresse
        // IP et l'agent utilisateur.
        $request = $this->requestStack->getCurrentRequest();
        $this->consentService->recordRegistrationConsent($user, $request, $request?->getLocale() ?? 'fr');

        return $profile;
    }

    /**
     * Étape 2/2 — le dossier part en vérification.
     *
     * Idempotente : rejouer l'étape sur un dossier déjà soumis ne lève rien.
     * Sans cela, un double-clic sur « Enregistrer et continuer » aurait
     * transformé la fin du parcours en erreur 500.
     */
    public function submitForVerification(ProviderProfile $profile): void
    {
        $workflow = $this->workflowRegistry->get($profile, 'provider_verification');

        if ($workflow->can($profile, 'submit')) {
            $workflow->apply($profile, 'submit');
            $this->entityManager->flush();
        }
    }

    /**
     * Dossier encore ouvert d'un utilisateur, s'il en a un.
     */
    public function draftProfileOf(User $user): ?ProviderProfile
    {
        return $this->providerProfiles->findOneByUser($user);
    }
}
