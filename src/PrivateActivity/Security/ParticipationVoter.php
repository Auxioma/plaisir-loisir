<?php

declare(strict_types=1);

namespace App\PrivateActivity\Security;

use App\PrivateActivity\Entity\Participation;
use App\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur une demande de participation (§33.2 du CDC — famille indicative
 * ACTIVITY_PARTICIPATE / MANAGE_PARTICIPANTS).
 *
 * - DECIDE : accepter/refuser une demande en attente — réservé à
 *            l'organisateur de l'activité.
 * - CANCEL : annuler sa propre participation — réservé au participant.
 *
 * @extends Voter<string, Participation>
 */
final class ParticipationVoter extends Voter
{
    public const DECIDE = 'DECIDE';
    public const CANCEL = 'CANCEL';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Participation && \in_array($attribute, [self::DECIDE, self::CANCEL], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::DECIDE => $subject->getPrivateActivity()?->getOrganizer() === $user,
            self::CANCEL => $subject->getParticipant() === $user,
            default => false,
        };
    }
}
