<?php

declare(strict_types=1);

namespace App\Tests\PrivateActivity\Entity;

use App\PrivateActivity\Entity\Invitation;
use App\PrivateActivity\Enum\InvitationStatus;
use PHPUnit\Framework\TestCase;

final class InvitationTest extends TestCase
{
    public function testDefaultStatusIsPending(): void
    {
        self::assertSame(InvitationStatus::Pending, (new Invitation())->getStatus());
    }

    public function testAcceptAndDecline(): void
    {
        $invitation = new Invitation();

        $invitation->accept();
        self::assertSame(InvitationStatus::Accepted, $invitation->getStatus());

        $invitation->decline();
        self::assertSame(InvitationStatus::Declined, $invitation->getStatus());
    }
}
