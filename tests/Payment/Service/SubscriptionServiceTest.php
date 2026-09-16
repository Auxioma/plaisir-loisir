<?php

declare(strict_types=1);

namespace App\Tests\Payment\Service;

use App\Notification\Entity\Notification;
use App\Notification\Enum\NotificationCategory;
use App\Notification\Repository\NotificationRepository;
use App\Notification\Service\NotificationService;
use App\Payment\Entity\Subscription;
use App\Payment\Entity\SubscriptionPlan;
use App\Payment\Enum\BillingPeriod;
use App\Payment\Enum\SubscriptionStatus;
use App\Payment\Repository\SubscriptionRepository;
use App\Payment\Service\SubscriptionService;
use App\Payment\Stripe\SubscriptionCheckoutResult;
use App\Payment\Stripe\SubscriptionGateway;
use App\Provider\Entity\ProviderProfile;
use App\User\Entity\User;
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

        $result = (new SubscriptionService($em, $subscriptions, $gateway, new NotificationService($em, $this->createStub(NotificationRepository::class))))
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

        (new SubscriptionService($em, $subscriptions, $this->createStub(SubscriptionGateway::class), new NotificationService($em, $this->createStub(NotificationRepository::class))))
            ->startCheckout($provider, new SubscriptionPlan(), 'https://example.test/succes', 'https://example.test/annule');
    }

    public function testCancelRejectsSomeoneOtherThanTheSubscriptionOwner(): void
    {
        $owner = new ProviderProfile();
        $subscription = (new Subscription())->setProvider($owner);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $this->expectException(\InvalidArgumentException::class);

        (new SubscriptionService($em, $this->createStub(SubscriptionRepository::class), $this->createStub(SubscriptionGateway::class), new NotificationService($em, $this->createStub(NotificationRepository::class))))
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

        (new SubscriptionService($em, $this->createStub(SubscriptionRepository::class), $gateway, new NotificationService($em, $this->createStub(NotificationRepository::class))))
            ->cancel($subscription, $owner, atPeriodEnd: true);

        self::assertTrue($subscription->isCancelAtPeriodEnd());
        self::assertSame(SubscriptionStatus::Active, $subscription->getStatus(), 'Reste actif jusqu\'à la fin de la période déjà payée.');
    }

    public function testCancelNotifiesTheProviderOwner(): void
    {
        $ownerUser = new User();
        $owner = (new ProviderProfile())->setUser($ownerUser);
        $subscription = (new Subscription())->setProvider($owner)->setStatus(SubscriptionStatus::Active);

        $notified = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity) use (&$notified): void {
            if ($entity instanceof Notification) {
                $notified[] = $entity;
            }
        });

        (new SubscriptionService($em, $this->createStub(SubscriptionRepository::class), $this->createStub(SubscriptionGateway::class), new NotificationService($em, $this->createStub(NotificationRepository::class))))
            ->cancel($subscription, $owner, atPeriodEnd: false);

        self::assertCount(1, $notified);
        self::assertSame($ownerUser, $notified[0]->getRecipient());
    }

    public function testSyncFromStripeSubscriptionUpdatesTheMatchingRow(): void
    {
        $subscription = (new Subscription())->setStripeSubscriptionId('sub_abc')->setStatus(SubscriptionStatus::Active);

        $subscriptions = $this->createMock(SubscriptionRepository::class);
        $subscriptions->expects(self::once())->method('findOneByStripeSubscriptionId')->with('sub_abc')->willReturn($subscription);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        (new SubscriptionService($em, $subscriptions, $this->createStub(SubscriptionGateway::class), new NotificationService($em, $this->createStub(NotificationRepository::class))))
            ->syncFromStripeSubscription('sub_abc', SubscriptionStatus::PastDue, null, null);

        self::assertSame(SubscriptionStatus::PastDue, $subscription->getStatus());
    }

    public function testSyncFromStripeSubscriptionIgnoresAnUnknownSubscription(): void
    {
        $subscriptions = $this->createStub(SubscriptionRepository::class);
        $subscriptions->method('findOneByStripeSubscriptionId')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        (new SubscriptionService($em, $subscriptions, $this->createStub(SubscriptionGateway::class), new NotificationService($em, $this->createStub(NotificationRepository::class))))
            ->syncFromStripeSubscription('sub_inconnu', SubscriptionStatus::Active, null, null);
    }

    /**
     * §15 du CDC (« Professionnels ») : abonnement et paiement font partie
     * des événements à notifier. C'est ici, dans la synchronisation
     * Stripe, que le statut change réellement (voir le commentaire de
     * syncFromStripeSubscription()).
     */
    public function testSyncFromStripeSubscriptionNotifiesOnPaymentFailure(): void
    {
        $owner = new User();
        $subscription = (new Subscription())
            ->setProvider((new ProviderProfile())->setUser($owner))
            ->setStripeSubscriptionId('sub_abc')
            ->setStatus(SubscriptionStatus::Active);

        $subscriptions = $this->createStub(SubscriptionRepository::class);
        $subscriptions->method('findOneByStripeSubscriptionId')->willReturn($subscription);

        $notified = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity) use (&$notified): void {
            if ($entity instanceof Notification) {
                $notified[] = $entity;
            }
        });

        (new SubscriptionService($em, $subscriptions, $this->createStub(SubscriptionGateway::class), new NotificationService($em, $this->createStub(NotificationRepository::class))))
            ->syncFromStripeSubscription('sub_abc', SubscriptionStatus::PastDue, null, null);

        self::assertCount(1, $notified);
        self::assertSame($owner, $notified[0]->getRecipient());
        self::assertSame(NotificationCategory::Payment, $notified[0]->getCategory());
        self::assertSame('Échec de paiement', $notified[0]->getTitle());
    }

    public function testSyncFromStripeSubscriptionDoesNotNotifyWhenStatusIsUnchanged(): void
    {
        $subscription = (new Subscription())
            ->setProvider((new ProviderProfile())->setUser(new User()))
            ->setStripeSubscriptionId('sub_abc')
            ->setStatus(SubscriptionStatus::Active);

        $subscriptions = $this->createStub(SubscriptionRepository::class);
        $subscriptions->method('findOneByStripeSubscriptionId')->willReturn($subscription);

        $notified = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity) use (&$notified): void {
            if ($entity instanceof Notification) {
                $notified[] = $entity;
            }
        });

        (new SubscriptionService($em, $subscriptions, $this->createStub(SubscriptionGateway::class), new NotificationService($em, $this->createStub(NotificationRepository::class))))
            ->syncFromStripeSubscription('sub_abc', SubscriptionStatus::Active, null, null);

        self::assertCount(0, $notified);
    }
}
