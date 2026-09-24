<?php

declare(strict_types=1);

namespace App\Tests\Payment\Security;

use App\Payment\Entity\Subscription;
use App\Payment\Security\SubscriptionVoter;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Repository\ProviderProfileRepository;
use App\User\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * §4 du CDC : un abonnement est une donnée financière, réservée à son
 * titulaire.
 */
final class SubscriptionVoterTest extends TestCase
{
    public function testTheOwningProviderCanViewAndManage(): void
    {
        $ownerProfile = new ProviderProfile();
        $ownerUser = new User();
        $subscription = (new Subscription())->setProvider($ownerProfile);

        $providers = $this->createMock(ProviderProfileRepository::class);
        $providers->expects(self::atLeastOnce())->method('findOneByUser')->with($ownerUser)->willReturn($ownerProfile);

        $voter = new SubscriptionVoter($providers);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($this->tokenFor($ownerUser), $subscription, [SubscriptionVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($this->tokenFor($ownerUser), $subscription, [SubscriptionVoter::MANAGE]));
    }

    public function testAnotherProviderCannotViewSomeoneElsesSubscription(): void
    {
        $subscription = (new Subscription())->setProvider(new ProviderProfile());
        $stranger = new User();

        $providers = $this->createMock(ProviderProfileRepository::class);
        $providers->expects(self::once())->method('findOneByUser')->with($stranger)->willReturn(new ProviderProfile());

        $voter = new SubscriptionVoter($providers);

        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($this->tokenFor($stranger), $subscription, [SubscriptionVoter::VIEW]));
    }

    private function tokenFor(User $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
