<?php

declare(strict_types=1);

namespace App\User\Service;

use App\Legal\Service\ConsentService;
use App\Provider\Service\ProviderOnboardingService;
use App\User\Entity\User;
use App\User\Enum\AccountType;
use App\User\Enum\UserStatus;
use App\User\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Logique métier de l'inscription : la création d'un utilisateur vit ici,
 * pas dans le contrôleur ni dans l'entité.
 */
final class RegistrationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository,
        // Dépendance assumée du domaine User vers le domaine Provider :
        // « s'inscrire comme professionnel ouvre un dossier prestataire » est
        // une règle métier, pas une affaire de contrôleur. La flèche va dans
        // ce sens et jamais dans l'autre.
        private readonly ProviderOnboardingService $providerOnboarding,
        // Même raisonnement pour le domaine Legal : « accepter les conditions
        // générales fait naître une preuve » est une règle métier.
        private readonly ConsentService $consentService,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Inscrit un nouvel utilisateur à partir des champs de la maquette.
     *
     * Un compte « Pro Prestataire » se distingue d'un compte client sur deux
     * points, et deux seulement : il porte ROLE_PROVIDER, et un dossier
     * prestataire vide lui est ouvert en brouillon. Tout le reste est
     * identique — la maquette d'inscription est la même pour les deux.
     *
     * @throws ConflictHttpException si un compte existe déjà avec cet e-mail
     */
    public function register(
        string $fullName,
        string $email,
        string $plainPassword,
        ?string $phone = null,
        AccountType $accountType = AccountType::Client,
    ): User {
        // Les e-mails sont comparés en minuscules : sans cela « Jean@x.fr » et
        // « jean@x.fr » créeraient deux comptes, et la connexion échouerait
        // une fois sur deux selon la casse saisie.
        $email = mb_strtolower(trim($email));

        if (null !== $this->userRepository->findOneBy(['email' => $email])) {
            throw new ConflictHttpException('Un compte existe déjà avec cet e-mail.');
        }

        [$lastName, $firstName] = self::splitFullName($fullName);

        $phone = null !== $phone ? trim($phone) : null;

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setPhone('' !== $phone ? $phone : null);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        // Pas encore de vérification d'e-mail : le compte est actif tout de
        // suite, sinon personne ne pourrait se connecter. À revoir quand la
        // confirmation par e-mail sera au programme.
        $user->setStatus(UserStatus::Active);

        if ($accountType->isProvider()) {
            // getRoles() ajoute toujours ROLE_USER : un prestataire reste un
            // utilisateur de la plateforme, il gagne seulement un rôle en plus.
            $user->setRoles(['ROLE_PROVIDER']);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        if ($accountType->isProvider()) {
            // Après le flush : le dossier référence l'utilisateur, qui doit
            // donc déjà exister en base.
            //
            // Le rôle seul n'engage rien : la publication d'une activité est
            // refusée tant que le dossier n'est pas « Verified »
            // (ActivityPublishingService). Se déclarer professionnel à
            // l'inscription ouvre donc l'espace pro, pas la mise en ligne.
            $this->providerOnboarding->startDraftProfile(
                $user,
                trim($firstName.' '.$lastName) ?: $user->getEmail(),
            );
        }

        // La case « J'accepte les conditions générales » était validée puis
        // oubliée : rien en base ne prouvait que qui que ce soit avait accepté
        // quoi que ce soit, alors que l'article 7.1 du RGPD l'exige. On
        // enregistre désormais la version acceptée, la date, l'adresse IP et
        // l'agent utilisateur.
        $request = $this->requestStack->getCurrentRequest();
        $this->consentService->recordRegistrationConsent(
            $user,
            $request,
            $request?->getLocale() ?? 'fr',
        );

        return $user;
    }

    /**
     * Découpe le champ unique de la maquette en nom puis prénom.
     *
     * La maquette ne propose qu'un seul champ, libellé « Nom & prénom », alors
     * que l'entité User en stocke deux. On suit l'ordre du libellé : le premier
     * mot est le NOM, tout le reste le prénom (« Dupont Jean Marie » -> nom
     * « Dupont », prénom « Jean Marie »). Saisie en un seul mot : il devient le
     * nom et le prénom reste vide, plutôt que d'inventer une découpe.
     *
     * @return array{0: string, 1: string} [nom, prénom]
     */
    public static function splitFullName(string $fullName): array
    {
        // Les espaces multiples et insécables sont normalisés avant découpe.
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $fullName));

        if ('' === $normalized) {
            return ['', ''];
        }

        $parts = explode(' ', $normalized, 2);

        return [
            mb_substr($parts[0], 0, 100),
            mb_substr($parts[1] ?? '', 0, 100),
        ];
    }
}
