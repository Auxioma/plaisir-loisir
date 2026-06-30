<?php

declare(strict_types=1);

namespace App\Tests\PrivateActivity\Service;

use App\PrivateActivity\Entity\Invitation;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\InvitationStatus;
use App\PrivateActivity\Repository\InvitationRepository;
use App\PrivateActivity\Service\PrivateActivityService;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class PrivateActivityServiceTest extends TestCase
{
    public function testCreatePersistsActivity(): void
    {
        $organizer = new User();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(PrivateActivity::class));
        $em->expects(self::once())->method('flush');

        $activity = (new PrivateActivityService($em, $this->createStub(InvitationRepository::class)))
            ->create($organizer, 'Pique-nique au parc');

        self::assertSame($organizer, $activity->getOrganizer());
        self::assertSame('Pique-nique au parc', $activity->getTitle());
    }

    public function testInviteAddsInvitationWhenOrganizer(): void
    {
        $organizer = new User();
        $activity = (new PrivateActivity())->setOrganizer($organizer);

        $invitations = $this->createStub(InvitationRepository::class);
        $invitations->method('findOneByActivityAndInvitee')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(Invitation::class));
        $em->expects(self::once())->method('flush');

        $invitee = new User();
        $invitation = (new PrivateActivityService($em, $invitations))->invite($activity, $organizer, $invitee);

        self::assertSame($invitee, $invitation->getInvitee());
        self::assertCount(1, $activity->getInvitations());
    }

    public function testInviteRejectsNonOrganizer(): void
    {
        $activity = (new PrivateActivity())->setOrganizer(new User());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);

        (new PrivateActivityService($em, $this->createStub(InvitationRepository::class)))
            ->invite($activity, new User(), new User());
    }

    public function testInviteRejectsDuplicate(): void
    {
        $organizer = new User();
        $activity = (new PrivateActivity())->setOrganizer($organizer);

        $invitations = $this->createStub(InvitationRepository::class);
        $invitations->method('findOneByActivityAndInvitee')->willReturn(new Invitation());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);

        (new PrivateActivityService($em, $invitations))->invite($activity, $organizer, new User());
    }

    public function testRespondAcceptsWhenInvitee(): void
    {
        $invitee = new User();
        $invitation = (new Invitation())->setInvitee($invitee);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        (new PrivateActivityService($em, $this->createStub(InvitationRepository::class)))
            ->respond($invitation, $invitee, true);

        self::assertSame(InvitationStatus::Accepted, $invitation->getStatus());
    }

    public function testRespondRejectsNonInvitee(): void
    {
        $invitation = (new Invitation())->setInvitee(new User());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $this->expectException(\InvalidArgumentException::class);

        (new PrivateActivityService($em, $this->createStub(InvitationRepository::class)))
            ->respond($invitation, new User(), true);
    }
}
