<?php

declare(strict_types=1);

namespace App\Tests\Notification\EventListener;

use App\Notification\Entity\Notification;
use App\Notification\Entity\NotificationPreference;
use App\Notification\EventListener\NotificationEmailListener;
use App\Notification\Message\SendNotificationEmail;
use App\Notification\Repository\NotificationPreferenceRepository;
use App\User\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class NotificationEmailListenerTest extends TestCase
{
    private function notificationFor(User $recipient): Notification
    {
        return (new Notification())
            ->setRecipient($recipient)
            ->setTitle('Réservation confirmée')
            ->setMessage('Votre réservation est confirmée.');
    }

    public function testDispatchesEmailWhenNoPreference(): void
    {
        $recipient = (new User())->setEmail('client@example.com');

        $preferences = $this->createStub(NotificationPreferenceRepository::class);
        $preferences->method('findOneByUser')->willReturn(null);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->with(self::isInstanceOf(SendNotificationEmail::class))
            ->willReturn(new Envelope(new \stdClass()));

        (new NotificationEmailListener($bus, $preferences))->onPostPersist($this->notificationFor($recipient));
    }

    public function testDoesNotDispatchWhenEmailDisabled(): void
    {
        $recipient = (new User())->setEmail('client@example.com');

        $preferences = $this->createStub(NotificationPreferenceRepository::class);
        $preferences->method('findOneByUser')->willReturn((new NotificationPreference())->setEmailEnabled(false));

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        (new NotificationEmailListener($bus, $preferences))->onPostPersist($this->notificationFor($recipient));
    }
}
