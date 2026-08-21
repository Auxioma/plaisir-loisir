<?php

declare(strict_types=1);

namespace App\Tests\User\Service;

use App\Legal\Service\ConsentService;
use App\Provider\Service\ProviderOnboardingService;
use App\User\Entity\User;
use App\User\Enum\AccountType;
use App\User\Enum\UserStatus;
use App\User\Repository\UserRepository;
use App\User\Service\RegistrationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
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

        $service = $this->service($em, $hasher, $userRepository);

        // Le formulaire de la maquette ne propose qu'un champ « Nom & prénom » :
        // le premier mot est le nom, le reste le prénom.
        $user = $service->register('Martin Bob', 'bob@example.com', 'plain-secret');

        self::assertSame('bob@example.com', $user->getEmail());
        self::assertSame('Bob', $user->getFirstName());
        self::assertSame('Martin', $user->getLastName());
        self::assertSame('hashed-password', $user->getPassword());
        self::assertSame(UserStatus::Active, $user->getStatus());
        self::assertNotContains('ROLE_PROVIDER', $user->getRoles());
    }

    /**
     * Un compte créé par la tuile « Pro Prestataire » doit se distinguer d'un
     * compte client : c'était le défaut silencieux corrigé le 18/08.
     */
    public function testRegisterAsProviderGrantsTheRoleAndOpensADraftProfile(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn(null);

        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed-password');

        $onboarding = $this->createMock(ProviderOnboardingService::class);
        $onboarding->expects(self::once())
            ->method('startDraftProfile')
            ->with(self::isInstanceOf(User::class), 'Bob Martin');

        // Une doublure simple suffit : ce test ne verifie pas les ecritures,
        // seulement le role et l'ouverture du dossier prestataire.
        $service = $this->service(
            $this->createStub(EntityManagerInterface::class),
            $hasher,
            $userRepository,
            $onboarding,
        );

        $user = $service->register('Martin Bob', 'pro@example.com', 'plain-secret', null, AccountType::Provider);

        self::assertContains('ROLE_PROVIDER', $user->getRoles());
    }

    /**
     * Assemble le service, ses dépendances hors sujet remplacées par des
     * doublures : ouvrir un dossier prestataire et tracer le consentement sont
     * testés ailleurs.
     */
    private function service(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        UserRepository $userRepository,
        ?ProviderOnboardingService $onboarding = null,
    ): RegistrationService {
        return new RegistrationService(
            $em,
            $hasher,
            $userRepository,
            $onboarding ?? $this->createStub(ProviderOnboardingService::class),
            $this->createStub(ConsentService::class),
            new RequestStack(),
        );
    }

    public function testRegisterThrowsWhenEmailAlreadyExists(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn(new User()); // email déjà pris

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');

        $service = $this->service($em, $this->createStub(UserPasswordHasherInterface::class), $userRepository);

        $this->expectException(ConflictHttpException::class);

        $service->register('Durand Eve', 'taken@example.com', 'plain-secret');
    }
}
