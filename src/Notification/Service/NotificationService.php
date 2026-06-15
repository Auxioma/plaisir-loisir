<?php

declare(strict_types=1);

namespace App\Notification\Service;

use App\Notification\Entity\Notification;
use App\Notification\Enum\NotificationCategory;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Logique métier des notifications in-app : création et marquage comme lues.
 */
final class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function notify(User $recipient, NotificationCategory $category, string $title, string $message): Notification
    {
        $notification = (new Notification())
            ->setRecipient($recipient)
            ->setCategory($category)
            ->setTitle($title)
            ->setMessage($message);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }

    public function markAsRead(Notification $notification): void
    {
        $notification->markAsRead();
        $this->entityManager->flush();
    }
}
