<?php

declare(strict_types=1);

namespace App\Tests\User\Service;

use App\User\Entity\User;
use App\User\Enum\UserStatus;
use App\User\Repository\UserRepository;
use App\User\Service\RegistrationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RegistrationServiceTest extends TestCase
{
    public function testRegisterCreatesHashedAndActiveUser(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn(null); // email disponible

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects(self::once())
            ->method('hashPassword')
            ->willReturn('hashed-password');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(User::class));
        $em->expects(self::once())->method('flush');

        $service = new RegistrationService($em, $hasher, $userRepository);

        $user = $service->register('bob@example.com', 'plain-secret', 'Bob', 'Martin');

        self::assertSame('bob@example.com', $user->getEmail());
        self::assertSame('Bob', $user->getFirstName());
        self::assertSame('Martin', $user->getLastName());
        self::assertSame('hashed-password', $user->getPassword());
        self::assertSame(UserStatus::Active, $user->getStatus());
    }

    public function testRegisterThrowsWhenEmailAlreadyExists(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn(new User()); // email déjà pris

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');

        $service = new RegistrationService(
            $em,
            $this->createStub(UserPasswordHasherInterface::class),
            $userRepository,
        );

        $this->expectException(ConflictHttpException::class);

        $service->register('taken@example.com', 'plain-secret', 'Eve', 'Durand');
    }
}
