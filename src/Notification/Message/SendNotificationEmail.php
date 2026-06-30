<?php

declare(strict_types=1);

namespace App\Notification\Message;

/**
 * Message Messenger : demande d'envoi d'un e-mail de notification (asynchrone).
 */
final class SendNotificationEmail
{
    public function __construct(
        public readonly string $recipientEmail,
        public readonly string $subject,
        public readonly string $body,
    ) {
    }
}
