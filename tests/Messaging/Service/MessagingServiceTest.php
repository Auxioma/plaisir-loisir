<?php

declare(strict_types=1);

namespace App\Tests\Messaging\Service;

use App\Messaging\Entity\Conversation;
use App\Messaging\Entity\Message;
use App\Messaging\Repository\ConversationRepository;
use App\Messaging\Service\MessagingService;
use App\Provider\Entity\ProviderProfile;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class MessagingServiceTest extends TestCase
{
    public function testOpenConversationReturnsExistingWhenPresent(): void
    {
        $existing = new Conversation();

        $conversations = $this->createStub(ConversationRepository::class);
        $conversations->method('findOneByClientAndProvider')->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $result = (new MessagingService($em, $conversations))->openConversation(new User(), new ProviderProfile());

        self::assertSame($existing, $result);
    }

    public function testOpenConversationCreatesWhenAbsent(): void
    {
        $client = new User();
        $provider = new ProviderProfile();

        $conversations = $this->createStub(ConversationRepository::class);
        $conversations->method('findOneByClientAndProvider')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(Conversation::class));
        $em->expects(self::once())->method('flush');

        $conversation = (new MessagingService($em, $conversations))->openConversation($client, $provider);

        self::assertSame($client, $conversation->getClient());
        self::assertSame($provider, $conversation->getProvider());
    }

    public function testSendMessageAddsMessageFromParticipant(): void
    {
        $client = new User();
        $conversation = (new Conversation())->setClient($client)->setProvider(new ProviderProfile());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(Message::class));
        $em->expects(self::once())->method('flush');

        $message = (new MessagingService($em, $this->createStub(ConversationRepository::class)))
            ->sendMessage($conversation, $client, 'Bonjour, est-ce disponible ?');

        self::assertSame($client, $message->getAuthor());
        self::assertSame('Bonjour, est-ce disponible ?', $message->getBody());
        self::assertCount(1, $conversation->getMessages());
    }

    public function testSendMessageRejectsNonParticipant(): void
    {
        $conversation = (new Conversation())->setClient(new User())->setProvider(new ProviderProfile());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);

        (new MessagingService($em, $this->createStub(ConversationRepository::class)))
            ->sendMessage($conversation, new User(), 'Coucou');
    }

    public function testMarkReadMarksOnlyOtherPartyMessages(): void
    {
        $client = new User();
        $providerOwner = new User();
        $conversation = (new Conversation())
            ->setClient($client)
            ->setProvider((new ProviderProfile())->setUser($providerOwner));

        $fromClient = (new Message())->setAuthor($client)->setBody('Question');
        $fromProvider = (new Message())->setAuthor($providerOwner)->setBody('Réponse');
        $conversation->addMessage($fromClient)->addMessage($fromProvider);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        // Le client lit : seuls les messages de l'annonceur passent à « lu ».
        (new MessagingService($em, $this->createStub(ConversationRepository::class)))
            ->markRead($conversation, $client);

        self::assertFalse($fromClient->isRead());
        self::assertTrue($fromProvider->isRead());
    }
}
