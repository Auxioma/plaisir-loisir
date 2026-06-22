<?php

declare(strict_types=1);

namespace App\Tests\Messaging\Entity;

use App\Messaging\Entity\Conversation;
use App\Messaging\Entity\Message;
use App\Provider\Entity\ProviderProfile;
use App\User\Entity\User;
use PHPUnit\Framework\TestCase;

final class ConversationTest extends TestCase
{
    public function testAddMessageLinksBothSidesAndAvoidsDuplicates(): void
    {
        $conversation = new Conversation();
        $message = new Message();

        $conversation->addMessage($message);
        $conversation->addMessage($message); // doublon ignoré

        self::assertCount(1, $conversation->getMessages());
        self::assertSame($conversation, $message->getConversation());
    }

    public function testHasParticipantRecognisesClientAndProviderOwner(): void
    {
        $client = new User();
        $providerOwner = new User();
        $provider = (new ProviderProfile())->setUser($providerOwner);

        $conversation = (new Conversation())->setClient($client)->setProvider($provider);

        self::assertTrue($conversation->hasParticipant($client));
        self::assertTrue($conversation->hasParticipant($providerOwner));
        self::assertFalse($conversation->hasParticipant(new User())); // un étranger
    }
}
