<?php

declare(strict_types=1);

namespace App\Tests\User\Service;

use App\User\Enum\SocialProvider;
use App\User\OAuth\SocialUser;
use App\User\Service\SocialEmailChallenge;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class SocialEmailChallengeTest extends TestCase
{
    public function testCodeIsBoundToTheIdentityAndConsumedOnceWithoutTouchingClassicCodes(): void
    {
        [$challenge, $session, $code] = $this->challenge();
        $session->set('password_reset_code', 'classic-reset');
        $session->set('email_verification_email', 'classic@example.test');
        self::assertNull($challenge->confirm($session, 'incorrect'));
        $identity = $challenge->confirm($session, strtolower($code));
        self::assertNotNull($identity);
        self::assertSame('facebook-subject', $identity->externalId);
        self::assertTrue($identity->emailVerified);
        self::assertNull($challenge->confirm($session, $code));
        self::assertSame('classic-reset', $session->get('password_reset_code'));
        self::assertSame('classic@example.test', $session->get('email_verification_email'));
    }

    public function testFiveWrongCodesInvalidateTheChallenge(): void
    {
        [$challenge, $session, $code] = $this->challenge();
        for ($i = 0; $i < 5; ++$i) {
            self::assertNull($challenge->confirm($session, 'incorrect'));
        }
        self::assertNull($challenge->confirm($session, $code));
    }

    public function testExpiredCodeCannotBeUsed(): void
    {
        [$challenge, $session, $code] = $this->challenge();
        $stored = $session->get('social_email_challenge');
        $stored['expires'] = time() - 1;
        $session->set('social_email_challenge', $stored);
        self::assertNull($challenge->confirm($session, $code));
        self::assertFalse($session->has('social_email_challenge'));
    }

    private function challenge(): array
    {
        $code = '';
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willReturnCallback(static function (Email $message) use (&$code): void {
            preg_match('/code ([A-F0-9]{8})/', $message->getTextBody(), $matches);
            $code = $matches[1];
        });
        $challenge = new SocialEmailChallenge($mailer);
        $session = new Session(new MockArraySessionStorage());
        $challenge->start($session, new SocialUser(SocialProvider::Facebook, 'facebook-subject', 'alice@example.test'));

        return [$challenge, $session, $code];
    }
}
