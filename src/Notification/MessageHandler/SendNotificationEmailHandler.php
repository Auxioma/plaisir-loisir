<?php

declare(strict_types=1);

namespace App\Notification\MessageHandler;

use App\Notification\Message\SendNotificationEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

/**
 * Envoie l'e-mail de notification via Mailer (exécuté en asynchrone par Messenger).
 */
#[AsMessageHandler]
final class SendNotificationEmailHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(SendNotificationEmail $message): void
    {
        $this->mailer->send(
            (new Email())
                ->to($message->recipientEmail)
                ->subject($message->subject)
                ->text($message->body),
        );
    }
}
