<?php

declare(strict_types=1);

namespace App\User\Service;

use App\Legal\Service\ConsentService;
use App\User\Entity\User;
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
        // Dépendance assumée du domaine User vers le domaine Legal : « accepter
        // les conditions générales fait naître une preuve » est une règle métier.
        private readonly ConsentService $consentService,
        private readonly RequestStack $requestStack,
        private readonly EmailVerificationService $emailVerification,
    ) {
    }

    /**
     * Inscrit un nouveau membre PARTICULIER à partir des champs de la maquette.
     *
     * Un professionnel ne passe jamais par ici (11/10) : son compte naît de
     * ProviderRegistrationService, avec son dossier et ses justificatifs.
     *
     * @throws ConflictHttpException si un compte existe déjà avec cet e-mail
     */
    public function register(
        string $fullName,
        string $email,
        string $plainPassword,
        ?string $phone = null,
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
        // Le compte reste en attente jusqu'à la vérification de l'adresse
        // (Lot I, 15/09) : AccountChecker refuse la connexion tant que le
        // code envoyé ci-dessous n'a pas été validé.
        $user->setStatus(UserStatus::Pending);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

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

        // Dernière étape : le code de vérification part une fois le compte
        // entièrement constitué.
        $this->emailVerification->sendCode($user);

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
