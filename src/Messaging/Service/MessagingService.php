<?php

declare(strict_types=1);

namespace App\Messaging\Service;

use App\Catalog\Entity\Service;
use App\Messaging\Entity\Conversation;
use App\Messaging\Entity\Message;
use App\Messaging\Repository\ConversationRepository;
use App\Provider\Entity\ProviderProfile;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Logique métier de la messagerie : ouverture d'un fil, envoi et lecture des messages.
 */
final class MessagingService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ConversationRepository $conversations,
    ) {
    }

    /**
     * Récupère le fil client/annonceur existant, ou le crée.
     */
    public function openConversation(User $client, ProviderProfile $provider, ?Service $service = null): Conversation
    {
        $existing = $this->conversations->findOneByClientAndProvider($client, $provider);
        if (null !== $existing) {
            return $existing;
        }

        $conversation = (new Conversation())
            ->setClient($client)
            ->setProvider($provider)
            ->setService($service);

        $this->entityManager->persist($conversation);
        $this->entityManager->flush();

        return $conversation;
    }

    /**
     * @throws \InvalidArgumentException si l'auteur ne participe pas à la conversation
     */
    public function sendMessage(Conversation $conversation, User $author, string $body): Message
    {
        if (!$conversation->hasParticipant($author)) {
            throw new \InvalidArgumentException('L\'auteur ne participe pas à cette conversation.');
        }

        $message = (new Message())
            ->setAuthor($author)
            ->setBody($body);
        $conversation->addMessage($message);

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $message;
    }

    /**
     * Marque comme lus les messages de la conversation qui ne viennent pas du lecteur.
     */
    public function markRead(Conversation $conversation, User $reader): void
    {
        foreach ($conversation->getMessages() as $message) {
            if ($message->getAuthor() !== $reader) {
                $message->markAsRead();
            }
        }

        $this->entityManager->flush();
    }
}
