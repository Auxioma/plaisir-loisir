<?php

declare(strict_types=1);

namespace App\Tests\Messaging\Entity;

use App\Messaging\Entity\Message;
use App\User\Entity\User;
use PHPUnit\Framework\TestCase;

final class MessageTest extends TestCase
{
    public function testIsUnreadByDefault(): void
    {
        self::assertFalse((new Message())->isRead());
    }

    public function testFieldsAndMarkAsRead(): void
    {
        $author = new User();
        $message = (new Message())->setAuthor($author)->setBody('Bonjour !');

        self::assertSame($author, $message->getAuthor());
        self::assertSame('Bonjour !', $message->getBody());

        $message->markAsRead();

        self::assertTrue($message->isRead());
        self::assertInstanceOf(\DateTimeImmutable::class, $message->getReadAt());
    }
}
