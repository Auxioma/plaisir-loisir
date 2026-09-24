<?php

declare(strict_types=1);

namespace App\Notification\Service;

use App\Notification\Entity\Notification;
use App\Notification\Enum\NotificationCategory;
use App\Notification\Repository\NotificationRepository;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Logique métier des notifications in-app : création et marquage comme lues.
 */
final class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationRepository $notifications,
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

    /**
     * Écran « Notifications » de l'espace compte, bouton « Tout marquer
     * comme lu ».
     *
     * Une UPDATE DQL en masse liait l'entité User comme paramètre : dans ce
     * chemin d'exécution (SingleTableDeleteUpdateExecutor), Doctrine ne
     * réapplique pas la conversion du type personnalisé « ulid » qu'un
     * SELECT applique normalement, et Postgres refusait la chaîne Crockford
     * brute pour sa colonne uuid. Le volume par utilisateur restant modeste,
     * un flush unique après avoir marqué chaque notification en mémoire
     * l'évite simplement.
     */
    public function markAllAsRead(User $recipient): void
    {
        foreach ($this->notifications->findUnreadByRecipient($recipient) as $notification) {
            $notification->markAsRead();
        }

        $this->entityManager->flush();
    }
}
