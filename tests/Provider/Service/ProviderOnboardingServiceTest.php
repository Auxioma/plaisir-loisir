<?php

declare(strict_types=1);

namespace App\Tests\Provider\Service;

use App\Provider\Entity\ProviderProfile;
use App\Provider\Repository\ProviderProfileRepository;
use App\Provider\Service\ProviderOnboardingService;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\WorkflowInterface;

final class ProviderOnboardingServiceTest extends TestCase
{
    public function testBecomeProviderCreatesProfileAndSubmitsToWorkflow(): void
    {
        $user = new User();

        $repository = $this->createStub(ProviderProfileRepository::class);
        $repository->method('findOneByUser')->willReturn(null); // pas encore prestataire

        // Le service doit déclencher la transition "submit" du workflow.
        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->expects(self::once())
            ->method('apply')
            ->with(self::isInstanceOf(ProviderProfile::class), 'submit')
            ->willReturn(new Marking());

        $registry = $this->createMock(Registry::class);
        $registry->expects(self::once())
            ->method('get')
            ->with(self::isInstanceOf(ProviderProfile::class), 'provider_verification')
            ->willReturn($workflow);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(ProviderProfile::class));
        $em->expects(self::once())->method('flush');

        $service = new ProviderOnboardingService($em, $repository, $registry);

        $profile = $service->becomeProvider($user, 'Studio Zen', 'Zen SARL', 'Cours de yoga');

        self::assertSame($user, $profile->getUser());
        self::assertSame('Studio Zen', $profile->getDisplayName());
        self::assertSame('Zen SARL', $profile->getCompanyName());
        self::assertSame('Cours de yoga', $profile->getBio());
    }

    public function testBecomeProviderThrowsWhenUserIsAlreadyProvider(): void
    {
        $user = new User();

        $repository = $this->createStub(ProviderProfileRepository::class);
        $repository->method('findOneByUser')->willReturn(new ProviderProfile());

        // Aucune transition ni persistance ne doit avoir lieu.
        $registry = $this->createMock(Registry::class);
        $registry->expects(self::never())->method('get');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');

        $service = new ProviderOnboardingService($em, $repository, $registry);

        $this->expectException(ConflictHttpException::class);

        $service->becomeProvider($user, 'Studio Zen', null, null);
    }
}
