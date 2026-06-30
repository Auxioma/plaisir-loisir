<?php

declare(strict_types=1);

namespace App\Tests\Notification\Entity;

use App\Notification\Entity\NotificationPreference;
use App\User\Entity\User;
use PHPUnit\Framework\TestCase;

final class NotificationPreferenceTest extends TestCase
{
    public function testChannelsEnabledByDefault(): void
    {
        $preference = new NotificationPreference();

        self::assertTrue($preference->isEmailEnabled());
        self::assertTrue($preference->isPushEnabled());
    }

    public function testTogglePreferences(): void
    {
        $user = new User();
        $preference = (new NotificationPreference())
            ->setUser($user)
            ->setEmailEnabled(false)
            ->setPushEnabled(false);

        self::assertSame($user, $preference->getUser());
        self::assertFalse($preference->isEmailEnabled());
        self::assertFalse($preference->isPushEnabled());
    }
}
