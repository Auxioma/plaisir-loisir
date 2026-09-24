<?php

declare(strict_types=1);

namespace App\Tests\PrivateActivity\Security;

use App\PrivateActivity\Entity\Invitation;
use App\PrivateActivity\Entity\Participation;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\ParticipationStatus;
use App\PrivateActivity\Enum\PrivateActivityVisibility;
use App\PrivateActivity\Repository\ParticipationRepository;
use App\PrivateActivity\Security\PrivateActivityVoter;
use App\User\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * §12.3 du CDC : les trois niveaux de visibilité, et §12.4/§25.1 : le lieu
 * exact réservé aux participants acceptés.
 */
final class PrivateActivityVoterTest extends TestCase
{
    public function testAPublicActivityIsVisibleToAnonymousVisitors(): void
    {
        $activity = (new PrivateActivity())->setOrganizer(new User())->setVisibility(PrivateActivityVisibility::Public);
        $voter = new PrivateActivityVoter($this->createStub(ParticipationRepository::class));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote(new NullToken(), $activity, [PrivateActivityVoter::VIEW]),
        );
    }

    public function testAMembersOnlyActivityIsHiddenFromAnonymousVisitors(): void
    {
        $activity = (new PrivateActivity())->setOrganizer(new User())->setVisibility(PrivateActivityVisibility::MembersOnly);
        $voter = new PrivateActivityVoter($this->createStub(ParticipationRepository::class));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote(new NullToken(), $activity, [PrivateActivityVoter::VIEW]),
        );
    }

    public function testAMembersOnlyActivityIsVisibleToAnyConnectedUser(): void
    {
        $activity = (new PrivateActivity())->setOrganizer(new User())->setVisibility(PrivateActivityVisibility::MembersOnly);
        $voter = new PrivateActivityVoter($this->createStub(ParticipationRepository::class));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor(new User()), $activity, [PrivateActivityVoter::VIEW]),
        );
    }

    public function testAPrivateActivityIsHiddenFromAStrangerMember(): void
    {
        $activity = (new PrivateActivity())->setOrganizer(new User())->setVisibility(PrivateActivityVisibility::Private);

        $participations = $this->createStub(ParticipationRepository::class);
        $participations->method('findOneByActivityAndParticipant')->willReturn(null);

        $voter = new PrivateActivityVoter($participations);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor(new User()), $activity, [PrivateActivityVoter::VIEW]),
        );
    }

    public function testAPrivateActivityIsVisibleToAnInvitee(): void
    {
        $activity = (new PrivateActivity())->setOrganizer(new User())->setVisibility(PrivateActivityVisibility::Private);
        $invitee = new User();
        $activity->addInvitation((new Invitation())->setInvitee($invitee));

        $participations = $this->createStub(ParticipationRepository::class);
        $participations->method('findOneByActivityAndParticipant')->willReturn(null);

        $voter = new PrivateActivityVoter($participations);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($invitee), $activity, [PrivateActivityVoter::VIEW]),
        );
    }

    public function testOnlyTheOrganizerCanManage(): void
    {
        $organizer = new User();
        $activity = (new PrivateActivity())->setOrganizer($organizer);
        $voter = new PrivateActivityVoter($this->createStub(ParticipationRepository::class));

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($this->tokenFor($organizer), $activity, [PrivateActivityVoter::MANAGE]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($this->tokenFor(new User()), $activity, [PrivateActivityVoter::MANAGE]));
    }

    public function testTheOrganizerCannotRequestToParticipateInTheirOwnActivity(): void
    {
        $organizer = new User();
        $activity = (new PrivateActivity())->setOrganizer($organizer);
        $voter = new PrivateActivityVoter($this->createStub(ParticipationRepository::class));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor($organizer), $activity, [PrivateActivityVoter::PARTICIPATE]),
        );
    }

    /**
     * §12.4, §25.1 : une demande encore en attente ne doit jamais voir le
     * lieu exact, seule une personne ACCEPTÉE le peut.
     */
    public function testExactLocationIsHiddenFromAPendingParticipant(): void
    {
        $activity = (new PrivateActivity())->setOrganizer(new User())->setShowExactAddress(true);
        $user = new User();

        $participations = $this->createStub(ParticipationRepository::class);
        $participations->method('findOneByActivityAndParticipant')
            ->willReturn((new Participation())->setStatus(ParticipationStatus::Pending));

        $voter = new PrivateActivityVoter($participations);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor($user), $activity, [PrivateActivityVoter::VIEW_EXACT_LOCATION]),
        );
    }

    public function testExactLocationIsVisibleToAnAcceptedParticipantWhenTheOrganizerAllowsIt(): void
    {
        $activity = (new PrivateActivity())->setOrganizer(new User())->setShowExactAddress(true);
        $user = new User();

        $participations = $this->createStub(ParticipationRepository::class);
        $participations->method('findOneByActivityAndParticipant')
            ->willReturn((new Participation())->setStatus(ParticipationStatus::Accepted));

        $voter = new PrivateActivityVoter($participations);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($user), $activity, [PrivateActivityVoter::VIEW_EXACT_LOCATION]),
        );
    }

    public function testExactLocationStaysHiddenFromAnAcceptedParticipantWhenTheOrganizerDisabledIt(): void
    {
        $activity = (new PrivateActivity())->setOrganizer(new User())->setShowExactAddress(false);
        $user = new User();

        $participations = $this->createStub(ParticipationRepository::class);
        $participations->method('findOneByActivityAndParticipant')
            ->willReturn((new Participation())->setStatus(ParticipationStatus::Accepted));

        $voter = new PrivateActivityVoter($participations);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor($user), $activity, [PrivateActivityVoter::VIEW_EXACT_LOCATION]),
        );
    }

    private function tokenFor(User $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
