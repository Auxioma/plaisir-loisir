<?php

declare(strict_types=1);

namespace App\Messaging\Security;

use App\Messaging\Entity\Conversation;
use App\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur une conversation (§4, §33.2 du CDC) : consulter et répondre sont
 * réservés aux deux participants, client et annonceur.
 *
 * @extends Voter<string, Conversation>
 */
final class ConversationVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const REPLY = 'REPLY';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Conversation
            && \in_array($attribute, [self::VIEW, self::REPLY], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $subject->hasParticipant($user);
    }
}
