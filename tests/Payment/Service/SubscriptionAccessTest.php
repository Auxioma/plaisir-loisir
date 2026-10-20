<?php

declare(strict_types=1);

namespace App\Tests\Payment\Service;

use App\Payment\Entity\Subscription;
use App\Payment\Entity\SubscriptionPlan;
use App\Payment\Enum\SubscriptionStatus;
use App\Payment\Repository\SubscriptionPlanRepository;
use App\Payment\Repository\SubscriptionRepository;
use App\Payment\Service\SubscriptionAccess;
use App\Provider\Entity\ProviderProfile;
use PHPUnit\Framework\TestCase;

/**
 * Verrou d'abonnement des professionnels (20/10) : actif dès qu'une offre
 * existe ; un abonnement actif ou en retard de paiement donne accès.
 */
final class SubscriptionAccessTest extends TestCase
{
    public function testNoPlanMeansNoLock(): void
    {
        self::assertTrue($this->access(plans: [], current: null)->hasAccess(new ProviderProfile()));
    }

    public function testTheSwitchTurnsTheLockOff(): void
    {
        self::assertTrue($this->access(plans: [new SubscriptionPlan()], current: null, required: false)->hasAccess(new ProviderProfile()));
    }

    public function testWithoutSubscriptionAccessIsRefused(): void
    {
        self::assertFalse($this->access(plans: [new SubscriptionPlan()], current: null)->hasAccess(new ProviderProfile()));
    }

    public function testOnlyARunningSubscriptionGivesAccess(): void
    {
        foreach ([
            [SubscriptionStatus::Active, true],
            [SubscriptionStatus::PastDue, true],
            [SubscriptionStatus::Incomplete, false],
        ] as [$status, $expected]) {
            $current = (new Subscription())->setStatus($status);
            self::assertSame($expected, $this->access([new SubscriptionPlan()], $current)->hasAccess(new ProviderProfile()), $status->value);
        }
    }

    /** @param list<SubscriptionPlan> $plans */
    private function access(array $plans, ?Subscription $current, bool $required = true): SubscriptionAccess
    {
        $planRepository = $this->createStub(SubscriptionPlanRepository::class);
        $planRepository->method('findActive')->willReturn($plans);
        $subscriptions = $this->createStub(SubscriptionRepository::class);
        $subscriptions->method('findCurrentFor')->willReturn($current);

        return new SubscriptionAccess($subscriptions, $planRepository, $required);
    }
}
