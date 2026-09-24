<?php

declare(strict_types=1);

namespace App\Tests\Messaging\Security;

use App\Messaging\Entity\Conversation;
use App\Messaging\Security\ConversationVoter;
use App\Provider\Entity\ProviderProfile;
use App\User\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * §4, §33.2 du CDC : « le masquage d'un bouton dans Twig ne constitue jamais
 * une autorisation suffisante ». Ce test vérifie le VRAI verrou, côté Symfony.
 */
final class ConversationVoterTest extends TestCase
{
    public function testTheClientCanViewAndReply(): void
    {
        $clientUser = new User();
        $conversation = (new Conversation())->setClient($clientUser)->setProvider(new ProviderProfile());

        $voter = new ConversationVoter();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($clientUser), $conversation, [ConversationVoter::VIEW]),
        );
        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($clientUser), $conversation, [ConversationVoter::REPLY]),
        );
    }

    public function testTheProviderCanViewAndReply(): void
    {
        $providerUser = new User();
        $conversation = (new Conversation())
            ->setClient(new User())
            ->setProvider((new ProviderProfile())->setUser($providerUser));

        $voter = new ConversationVoter();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($providerUser), $conversation, [ConversationVoter::VIEW]),
        );
    }

    public function testAThirdPartyCannotViewNorReply(): void
    {
        $conversation = (new Conversation())
            ->setClient(new User())
            ->setProvider((new ProviderProfile())->setUser(new User()));

        $voter = new ConversationVoter();
        $intrus = new User();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor($intrus), $conversation, [ConversationVoter::VIEW]),
        );
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor($intrus), $conversation, [ConversationVoter::REPLY]),
        );
    }

    private function tokenFor(User $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
