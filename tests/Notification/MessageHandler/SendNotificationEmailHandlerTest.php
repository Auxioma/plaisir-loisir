<?php

declare(strict_types=1);

namespace App\Tests\Notification\MessageHandler;

use App\Notification\Message\SendNotificationEmail;
use App\Notification\MessageHandler\SendNotificationEmailHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class SendNotificationEmailHandlerTest extends TestCase
{
    public function testInvokeSendsEmailToRecipient(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->with(self::callback(
            static function (object $email): bool {
                return $email instanceof Email
                    && 'Réservation confirmée' === $email->getSubject()
                    && 'client@example.com' === ($email->getTo()[0] ?? null)?->getAddress();
            },
        ));

        $handler = new SendNotificationEmailHandler($mailer);
        $handler(new SendNotificationEmail('client@example.com', 'Réservation confirmée', 'À bientôt !'));
    }
}
