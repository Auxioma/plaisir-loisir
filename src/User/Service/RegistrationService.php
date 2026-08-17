<?php

declare(strict_types=1);

namespace App\User\Service;

use App\User\Entity\User;
use App\User\Enum\UserStatus;
use App\User\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
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
    ) {
    }

    /**
     * Inscrit un nouvel utilisateur à partir des champs de la maquette.
     *
     * @throws ConflictHttpException si un compte existe déjà avec cet e-mail
     */
    public function register(string $fullName, string $email, string $plainPassword, ?string $phone = null): User
    {
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

        $this->entityManager->persist($user);
        $this->entityManager->flush();

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
