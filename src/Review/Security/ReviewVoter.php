<?php

declare(strict_types=1);

namespace App\Review\Security;

use App\Quote\Entity\Quote;
use App\Quote\Enum\QuoteStatus;
use App\Review\Entity\Review;
use App\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur les avis (§4, §33.2 du CDC).
 *
 * - CREATE : déposer un avis sur un devis — réservé au client à l'origine de
 *            la demande, et seulement une fois le devis accepté.
 * - REPLY  : répondre à un avis reçu — réservé au prestataire noté.
 *
 * @extends Voter<string, Quote|Review>
 */
final class ReviewVoter extends Voter
{
    public const CREATE = 'CREATE';
    public const REPLY = 'REPLY';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return (self::CREATE === $attribute && $subject instanceof Quote)
            || (self::REPLY === $attribute && $subject instanceof Review);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if (self::CREATE === $attribute && $subject instanceof Quote) {
            return QuoteStatus::Accepted === $subject->getStatus()
                && $subject->getServiceRequest()?->getClient() === $user;
        }

        if (self::REPLY === $attribute && $subject instanceof Review) {
            return $subject->getProvider()?->getUser() === $user;
        }

        return false;
    }
}
