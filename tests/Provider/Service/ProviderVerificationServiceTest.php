<?php

declare(strict_types=1);

namespace App\Tests\Provider\Service;

use App\Provider\Entity\ProviderProfile;
use App\Provider\Service\ProviderVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\WorkflowInterface;

final class ProviderVerificationServiceTest extends TestCase
{
    public function testApproveAppliesTransition(): void
    {
        $provider = new ProviderProfile();

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->expects(self::once())->method('can')->with($provider, 'approve')->willReturn(true);
        $workflow->expects(self::once())->method('apply')->with($provider, 'approve')->willReturn(new Marking());

        $registry = $this->createMock(Registry::class);
        $registry->expects(self::once())->method('get')->with($provider, 'provider_verification')->willReturn($workflow);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        (new ProviderVerificationService($em, $registry))->approve($provider);
    }

    public function testRejectAppliesTransition(): void
    {
        $provider = new ProviderProfile();

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->expects(self::once())->method('can')->with($provider, 'reject')->willReturn(true);
        $workflow->expects(self::once())->method('apply')->with($provider, 'reject')->willReturn(new Marking());

        $registry = $this->createMock(Registry::class);
        $registry->expects(self::once())->method('get')->willReturn($workflow);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        (new ProviderVerificationService($em, $registry))->reject($provider);
    }

    public function testApproveRejectsImpossibleTransition(): void
    {
        $provider = new ProviderProfile();

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->expects(self::once())->method('can')->willReturn(false);
        $workflow->expects(self::never())->method('apply');

        $registry = $this->createMock(Registry::class);
        $registry->expects(self::once())->method('get')->willReturn($workflow);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $this->expectException(\InvalidArgumentException::class);

        (new ProviderVerificationService($em, $registry))->approve($provider);
    }
}
