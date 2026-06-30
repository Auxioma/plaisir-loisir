<?php

declare(strict_types=1);

namespace App\Tests\Catalog\Service;

use App\Catalog\Entity\Service;
use App\Catalog\Enum\ServiceStatus;
use App\Catalog\Service\ActivityPublishingService;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderStatus;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ActivityPublishingServiceTest extends TestCase
{
    private function verifiedProvider(): ProviderProfile
    {
        return (new ProviderProfile())->setStatus(ProviderStatus::Verified);
    }

    public function testPublishDraftOfVerifiedProvider(): void
    {
        $service = (new Service())->setProvider($this->verifiedProvider()); // brouillon par défaut

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        (new ActivityPublishingService($em))->publish($service);

        self::assertSame(ServiceStatus::Published, $service->getStatus());
    }

    public function testPublishRejectsUnverifiedProvider(): void
    {
        $service = (new Service())->setProvider(new ProviderProfile()); // annonceur non vérifié

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $this->expectException(\InvalidArgumentException::class);

        (new ActivityPublishingService($em))->publish($service);
    }

    public function testPublishRejectsNonDraftService(): void
    {
        $service = (new Service())->setProvider($this->verifiedProvider())->setStatus(ServiceStatus::Published);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $this->expectException(\InvalidArgumentException::class);

        (new ActivityPublishingService($em))->publish($service);
    }

    public function testArchiveSetsArchived(): void
    {
        $service = (new Service())->setStatus(ServiceStatus::Published);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        (new ActivityPublishingService($em))->archive($service);

        self::assertSame(ServiceStatus::Archived, $service->getStatus());
    }
}
