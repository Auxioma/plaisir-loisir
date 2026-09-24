<?php

declare(strict_types=1);

namespace App\Tests\Quote\Security;

use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderStatus;
use App\Provider\Repository\ProviderProfileRepository;
use App\Quote\Entity\ServiceRequest;
use App\Quote\Security\ServiceRequestVoter;
use App\User\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * §4, §33.2 du CDC : « le masquage d'un bouton dans Twig ne constitue jamais
 * une autorisation suffisante ». Ce test vérifie le VRAI verrou, côté Symfony.
 */
final class ServiceRequestVoterTest extends TestCase
{
    public function testOwnerCanManageTheirOwnRequest(): void
    {
        $client = new User();
        $request = (new ServiceRequest())->setClient($client);

        $voter = new ServiceRequestVoter($this->createStub(ProviderProfileRepository::class));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($client), $request, [ServiceRequestVoter::MANAGE]),
        );
    }

    public function testAnotherClientCannotManageTheRequest(): void
    {
        $owner = new User();
        $someoneElse = new User();
        $request = (new ServiceRequest())->setClient($owner);

        $voter = new ServiceRequestVoter($this->createStub(ProviderProfileRepository::class));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor($someoneElse), $request, [ServiceRequestVoter::MANAGE]),
        );
    }

    public function testOnlyAVerifiedProviderCanSubmitAQuote(): void
    {
        $draftProviderUser = new User();
        $draftProfile = (new ProviderProfile())->setStatus(ProviderStatus::PendingVerification);

        $providers = $this->createStub(ProviderProfileRepository::class);
        $providers->method('findOneByUser')->willReturn($draftProfile);

        $request = (new ServiceRequest())->setClient(new User());
        $voter = new ServiceRequestVoter($providers);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor($draftProviderUser), $request, [ServiceRequestVoter::SUBMIT_QUOTE]),
            'Un dossier encore en vérification ne doit pas pouvoir déposer de devis.',
        );
    }

    /**
     * Double verrou (voir CLAUDE.md) : le rôle ouvre l'espace professionnel
     * (ici, consulter une demande), la vérification ouvre le droit d'agir.
     * Un dossier encore en vérification doit donc pouvoir VOIR la demande,
     * même s'il ne peut pas encore y répondre (test précédent).
     */
    public function testAnUnverifiedProviderCanStillViewTheRequest(): void
    {
        $draftProviderUser = new User();
        $draftProfile = (new ProviderProfile())->setStatus(ProviderStatus::PendingVerification);

        $providers = $this->createStub(ProviderProfileRepository::class);
        $providers->method('findOneByUser')->willReturn($draftProfile);

        $request = (new ServiceRequest())->setClient(new User());
        $voter = new ServiceRequestVoter($providers);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($draftProviderUser), $request, [ServiceRequestVoter::VIEW]),
        );
    }

    public function testAVerifiedProviderCanSubmitAQuote(): void
    {
        $providerUser = new User();
        $verifiedProfile = (new ProviderProfile())->setStatus(ProviderStatus::Verified);

        $providers = $this->createStub(ProviderProfileRepository::class);
        $providers->method('findOneByUser')->willReturn($verifiedProfile);

        $request = (new ServiceRequest())->setClient(new User());
        $voter = new ServiceRequestVoter($providers);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($providerUser), $request, [ServiceRequestVoter::SUBMIT_QUOTE]),
        );
    }

    public function testAVisitorWithNoProviderProfileCannotViewOrSubmit(): void
    {
        $providers = $this->createStub(ProviderProfileRepository::class);
        $providers->method('findOneByUser')->willReturn(null);

        $client = new User();
        $request = (new ServiceRequest())->setClient(new User());
        $voter = new ServiceRequestVoter($providers);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor($client), $request, [ServiceRequestVoter::VIEW]),
        );
    }

    private function tokenFor(User $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
