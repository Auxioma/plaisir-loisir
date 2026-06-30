<?php

declare(strict_types=1);

namespace App\Notification\EventListener;

use App\Notification\Entity\Notification;
use App\Notification\Message\SendNotificationEmail;
use App\Notification\Repository\NotificationPreferenceRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * À chaque notification enregistrée, demande l'envoi de l'e-mail correspondant
 * (sauf si l'utilisateur a désactivé l'e-mail). Découplé de NotificationService.
 */
#[AsEntityListener(event: Events::postPersist, method: 'onPostPersist', entity: Notification::class)]
final class NotificationEmailListener
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly NotificationPreferenceRepository $preferences,
    ) {
    }

    public function onPostPersist(Notification $notification): void
    {
        $recipient = $notification->getRecipient();
        if (null === $recipient) {
            return;
        }

        $preference = $this->preferences->findOneByUser($recipient);
        if (null !== $preference && !$preference->isEmailEnabled()) {
            return;
        }

        $this->bus->dispatch(new SendNotificationEmail(
            $recipient->getEmail(),
            $notification->getTitle(),
            $notification->getMessage(),
        ));
    }
}
