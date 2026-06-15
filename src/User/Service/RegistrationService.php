<?php

declare(strict_types=1);

namespace App\User\Service;

use App\User\Dto\RegisterUserInput;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use App\User\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Logique métier de l'inscription : la création d'un utilisateur vit ici,
 * pas dans le contrôleur/State ni dans l'entité.
 */
final class RegistrationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function register(RegisterUserInput $input): User
    {
        if (null !== $this->userRepository->findOneBy(['email' => $input->email])) {
            throw new ConflictHttpException('Un compte existe déjà avec cet email.');
        }

        $user = new User();
        $user->setEmail($input->email);
        $user->setFirstName($input->firstName);
        $user->setLastName($input->lastName);
        $user->setPassword($this->passwordHasher->hashPassword($user, $input->plainPassword));
        $user->setStatus(UserStatus::Active);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
