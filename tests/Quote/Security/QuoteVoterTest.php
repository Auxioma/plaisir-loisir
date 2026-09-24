<?php

declare(strict_types=1);

namespace App\Tests\Quote\Security;

use App\Provider\Entity\ProviderProfile;
use App\Provider\Repository\ProviderProfileRepository;
use App\Quote\Entity\Quote;
use App\Quote\Entity\ServiceRequest;
use App\Quote\Security\QuoteVoter;
use App\User\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class QuoteVoterTest extends TestCase
{
    public function testTheClientWhoPostedTheRequestCanDecide(): void
    {
        $client = new User();
        $request = (new ServiceRequest())->setClient($client);
        $quote = (new Quote())->setServiceRequest($request)->setProvider(new ProviderProfile());

        $voter = new QuoteVoter($this->createStub(ProviderProfileRepository::class));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($client), $quote, [QuoteVoter::DECIDE]),
        );
    }

    public function testTheProvidingProfessionalCannotDecide(): void
    {
        $client = new User();
        $providerUser = new User();
        $providerProfile = new ProviderProfile();

        $request = (new ServiceRequest())->setClient($client);
        $quote = (new Quote())->setServiceRequest($request)->setProvider($providerProfile);

        $providers = $this->createStub(ProviderProfileRepository::class);
        $providers->method('findOneByUser')->willReturn($providerProfile);

        $voter = new QuoteVoter($providers);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor($providerUser), $quote, [QuoteVoter::DECIDE]),
            'Le devis n\'appartient pas au professionnel : accepter/refuser est réservé au client.',
        );
    }

    public function testTheProvidingProfessionalCanViewTheirOwnQuote(): void
    {
        $client = new User();
        $providerUser = new User();
        $providerProfile = new ProviderProfile();

        $request = (new ServiceRequest())->setClient($client);
        $quote = (new Quote())->setServiceRequest($request)->setProvider($providerProfile);

        $providers = $this->createStub(ProviderProfileRepository::class);
        $providers->method('findOneByUser')->willReturn($providerProfile);

        $voter = new QuoteVoter($providers);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($providerUser), $quote, [QuoteVoter::VIEW]),
        );
    }

    public function testAThirdPartyCannotViewTheQuote(): void
    {
        $request = (new ServiceRequest())->setClient(new User());
        $quote = (new Quote())->setServiceRequest($request)->setProvider(new ProviderProfile());

        $providers = $this->createStub(ProviderProfileRepository::class);
        $providers->method('findOneByUser')->willReturn(null);

        $voter = new QuoteVoter($providers);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor(new User()), $quote, [QuoteVoter::VIEW]),
        );
    }

    private function tokenFor(User $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
