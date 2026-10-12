<?php

declare(strict_types=1);

namespace App\Notification\EventListener;

use App\Notification\Entity\Notification;
use App\Notification\Message\SendNotificationEmail;
use App\Notification\Repository\NotificationPreferenceRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
        private readonly UrlGeneratorInterface $urls,
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

        // Lien vers l'espace du destinataire (12/10) : rendu en bouton dans
        // l'e-mail HTML (HtmlEmailSubscriber).
        $link = $this->urls->generate($recipient->isProvider() ? 'app_pro_notifications' : 'app_account_notifications', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->bus->dispatch(new SendNotificationEmail(
            $recipient->getEmail(),
            $notification->getTitle(),
            sprintf("Bonjour %s,\n\n%s\n\nVoir sur TrouveMoi : %s\n\nL'équipe TrouveMoi Plaisirs & Loisirs", $recipient->getFirstName(), $notification->getMessage(), $link),
        ));
    }
}
