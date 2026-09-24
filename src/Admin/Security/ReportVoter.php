<?php

declare(strict_types=1);

namespace App\Admin\Security;

use App\Admin\Entity\Report;
use App\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur un signalement (§33.2 du CDC : famille indicative
 * REPORT_VIEW / PROCESS).
 *
 * - VIEW    : la personne qui l'a déposé, ou un administrateur.
 * - PROCESS : réservé à un administrateur. Redondant avec la protection déjà
 *             posée sur tout /admin (ROLE_ADMIN), mais explicite : §33.2 le
 *             demande nommément, et un Voter dédié survivra si /admin
 *             s'ouvre un jour à un rôle « Modérateur » plus restreint (§4 du
 *             CDC le prévoit, non créé à ce stade).
 *
 * @extends Voter<string, Report>
 */
final class ReportVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const PROCESS = 'PROCESS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Report && \in_array($attribute, [self::VIEW, self::PROCESS], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        if (\in_array('ROLE_ADMIN', $token->getRoleNames(), true)) {
            return true;
        }

        return self::VIEW === $attribute && $subject->getReporter() === $user;
    }
}
