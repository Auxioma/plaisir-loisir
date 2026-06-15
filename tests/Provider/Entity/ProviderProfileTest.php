<?php

declare(strict_types=1);

namespace App\Tests\Provider\Entity;

use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderStatus;
use PHPUnit\Framework\TestCase;

final class ProviderProfileTest extends TestCase
{
    public function testDefaultStatusIsDraft(): void
    {
        self::assertSame(ProviderStatus::Draft, (new ProviderProfile())->getStatus());
    }

    public function testGetMarkingReflectsStatusValue(): void
    {
        $profile = new ProviderProfile();
        self::assertSame('draft', $profile->getMarking());

        $profile->setStatus(ProviderStatus::Verified);
        self::assertSame('verified', $profile->getMarking());
    }

    public function testSetMarkingUpdatesStatusFromString(): void
    {
        $profile = new ProviderProfile();

        $profile->setMarking('pending_verification');

        self::assertSame(ProviderStatus::PendingVerification, $profile->getStatus());
    }

    public function testSetMarkingWithUnknownPlaceThrows(): void
    {
        // La place inconnue passe par ProviderStatus::from() qui lève \ValueError.
        $this->expectException(\ValueError::class);

        (new ProviderProfile())->setMarking('not-a-real-place');
    }
}
