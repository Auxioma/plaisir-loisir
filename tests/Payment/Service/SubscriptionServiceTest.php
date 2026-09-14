<?php

declare(strict_types=1);

namespace App\Tests\Payment\Service;

use App\Payment\Entity\Subscription;
use App\Payment\Entity\SubscriptionPlan;
use App\Payment\Enum\BillingPeriod;
use App\Payment\Enum\SubscriptionStatus;
use App\Payment\Repository\SubscriptionRepository;
use App\Payment\Service\SubscriptionService;
use App\Payment\Stripe\SubscriptionCheckoutResult;
use App\Payment\Stripe\SubscriptionGateway;
use App\Provider\Entity\ProviderProfile;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class SubscriptionServiceTest extends TestCase
{
    public function testStartCheckoutWithTheMockGatewayActivatesImmediately(): void
    {
        $provider = new ProviderProfile();
        $plan = (new SubscriptionPlan())->setBillingPeriod(BillingPeriod::Monthly);

        $subscriptions = $this->createStub(SubscriptionRepository::class);
        $subscriptions->method('findCurrentFor')->willReturn(null);

        $gateway = $this->createStub(SubscriptionGateway::class);
        $gateway->method('startCheckout')->willReturn(new SubscriptionCheckoutResult(
            stripeCustomerId: 'mock_cus_123',
            redirectUrl: 'https://example.test/succes',
            alreadyActive: true,
            stripeSubscriptionId: 'mock_sub_123',
        ));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(Subscription::class));
        $em->expects(self::once())->method('flush');

        $result = (new SubscriptionService($em, $subscriptions, $gateway))
            ->startCheckout($provider, $plan, 'https://example.test/succes', 'https://example.test/annule');

        self::assertTrue($result->alreadyActive);
    }

    public function testStartCheckoutRejectsASecondSubscriptionForTheSameProvider(): void
    {
        $provider = new ProviderProfile();

        $subscriptions = $this->createStub(SubscriptionRepository::class);
        $subscriptions->method('findCurrentFor')->willReturn(new Subscription());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);

        (new SubscriptionService($em, $subscriptions, $this->createStub(SubscriptionGateway::class)))
            ->startCheckout($provider, new SubscriptionPlan(), 'https://example.test/succes', 'https://example.test/annule');
    }

    public function testCancelRejectsSomeoneOtherThanTheSubscriptionOwner(): void
    {
        $owner = new ProviderProfile();
        $subscription = (new Subscription())->setProvider($owner);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $this->expectException(\InvalidArgumentException::class);

        (new SubscriptionService($em, $this->createStub(SubscriptionRepository::class), $this->createStub(SubscriptionGateway::class)))
            ->cancel($subscription, new ProviderProfile());
    }

    public function testCancelAtPeriodEndMarksTheFlagWithoutChangingStatusImmediately(): void
    {
        $owner = new ProviderProfile();
        $subscription = (new Subscription())
            ->setProvider($owner)
            ->setStripeSubscriptionId('mock_sub_123')
            ->setStatus(SubscriptionStatus::Active);

        $gateway = $this->createMock(SubscriptionGateway::class);
        $gateway->expects(self::once())->method('cancelSubscription')->with('mock_sub_123', true);

        $em = $this->createStub(EntityManagerInterface::class);

        (new SubscriptionService($em, $this->createStub(SubscriptionRepository::class), $gateway))
            ->cancel($subscription, $owner, atPeriodEnd: true);

        self::assertTrue($subscription->isCancelAtPeriodEnd());
        self::assertSame(SubscriptionStatus::Active, $subscription->getStatus(), 'Reste actif jusqu\'à la fin de la période déjà payée.');
    }

    public function testSyncFromStripeSubscriptionUpdatesTheMatchingRow(): void
    {
        $subscription = (new Subscription())->setStripeSubscriptionId('sub_abc')->setStatus(SubscriptionStatus::Active);

        $subscriptions = $this->createMock(SubscriptionRepository::class);
        $subscriptions->expects(self::once())->method('findOneByStripeSubscriptionId')->with('sub_abc')->willReturn($subscription);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        (new SubscriptionService($em, $subscriptions, $this->createStub(SubscriptionGateway::class)))
            ->syncFromStripeSubscription('sub_abc', SubscriptionStatus::PastDue, null, null);

        self::assertSame(SubscriptionStatus::PastDue, $subscription->getStatus());
    }

    public function testSyncFromStripeSubscriptionIgnoresAnUnknownSubscription(): void
    {
        $subscriptions = $this->createStub(SubscriptionRepository::class);
        $subscriptions->method('findOneByStripeSubscriptionId')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        (new SubscriptionService($em, $subscriptions, $this->createStub(SubscriptionGateway::class)))
            ->syncFromStripeSubscription('sub_inconnu', SubscriptionStatus::Active, null, null);
    }
}
