<?php

declare(strict_types=1);

namespace App\Tests\PrivateActivity\Entity;

use App\PrivateActivity\Entity\Invitation;
use App\PrivateActivity\Entity\PrivateActivity;
use App\User\Entity\User;
use PHPUnit\Framework\TestCase;

final class PrivateActivityTest extends TestCase
{
    public function testBasicFieldsAndEmptyInvitations(): void
    {
        $organizer = new User();
        $activity = (new PrivateActivity())->setOrganizer($organizer)->setTitle('Rando dimanche');

        self::assertSame($organizer, $activity->getOrganizer());
        self::assertSame('Rando dimanche', $activity->getTitle());
        self::assertCount(0, $activity->getInvitations());
    }

    public function testAddInvitationLinksBothSidesAndAvoidsDuplicates(): void
    {
        $activity = new PrivateActivity();
        $invitation = new Invitation();

        $activity->addInvitation($invitation);
        $activity->addInvitation($invitation); // doublon ignoré

        self::assertCount(1, $activity->getInvitations());
        self::assertSame($activity, $invitation->getPrivateActivity());
    }
}
