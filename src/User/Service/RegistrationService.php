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
     * Inscrit un nouvel utilisateur.
     *
     * @throws ConflictHttpException si un compte existe déjà avec cet email
     */
    public function register(string $email, string $plainPassword, string $firstName, string $lastName): User
    {
        if (null !== $this->userRepository->findOneBy(['email' => $email])) {
            throw new ConflictHttpException('Un compte existe déjà avec cet email.');
        }

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->setStatus(UserStatus::Active);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
