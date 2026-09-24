<?php

declare(strict_types=1);

namespace App\PrivateActivity\Security;

use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\ParticipationStatus;
use App\PrivateActivity\Enum\PrivateActivityStatus;
use App\PrivateActivity\Enum\PrivateActivityVisibility;
use App\PrivateActivity\Repository\ParticipationRepository;
use App\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur une activité privée (§4, §33.2 du CDC — familles indicatives
 * ACTIVITY_VIEW / EDIT / CANCEL et ACTIVITY_PARTICIPATE).
 *
 * - VIEW               : dépend de la visibilité (§12.3) — PUBLIC pour tous
 *                         y compris un visiteur non connecté ; MEMBERS_ONLY
 *                         pour tout compte connecté ; PRIVATE réservée à
 *                         l'organisateur et aux personnes invitées ou déjà
 *                         inscrites (peu importe leur statut : une demande
 *                         refusée garde le droit de voir la page qu'elle a
 *                         demandée).
 * - MANAGE             : annuler l'activité, décider des demandes en attente
 *                         — réservé à l'organisateur.
 * - PARTICIPATE        : demander à participer — tout compte connecté qui
 *                         n'est pas l'organisateur. Capacité, doublon,
 *                         activité annulée : ce sont des règles métier, pas
 *                         des droits d'accès, laissées à
 *                         PrivateActivityService (même principe que
 *                         ServiceRequestVoter::SUBMIT_QUOTE).
 * - VIEW_EXACT_LOCATION : le lieu de rendez-vous exact (§12.4, §25.1) —
 *                         l'organisateur, ou un participant ACCEPTÉ si
 *                         `showExactAddress` est vrai. Jamais à une demande
 *                         encore en attente ou en liste d'attente.
 * - VIEW_ALBUM          : voir/déposer des photos dans l'album de l'activité
 *                         (§4 de docs/corrections-client-2026-07-27.md) —
 *                         l'organisateur, ou un participant ACCEPTÉ (« chaque
 *                         membre » du document). Pas les demandes en attente
 *                         ou en liste d'attente : elles n'ont pas encore pris
 *                         part à la sortie que l'album illustre.
 *
 * @extends Voter<string, PrivateActivity>
 */
final class PrivateActivityVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const MANAGE = 'MANAGE';
    public const PARTICIPATE = 'PARTICIPATE';
    public const VIEW_EXACT_LOCATION = 'VIEW_EXACT_LOCATION';
    public const VIEW_ALBUM = 'VIEW_ALBUM';

    public function __construct(
        private readonly ParticipationRepository $participations,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof PrivateActivity
            && \in_array($attribute, [self::VIEW, self::MANAGE, self::PARTICIPATE, self::VIEW_EXACT_LOCATION, self::VIEW_ALBUM], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        $isOrganizer = $user instanceof User && $subject->getOrganizer() === $user;

        return match ($attribute) {
            self::VIEW => $this->canView($subject, $user, $isOrganizer),
            self::MANAGE => $isOrganizer,
            self::PARTICIPATE => $user instanceof User && !$isOrganizer && PrivateActivityStatus::Cancelled !== $subject->getStatus(),
            self::VIEW_EXACT_LOCATION => $isOrganizer || ($subject->showsExactAddress() && $user instanceof User && $this->isAcceptedParticipant($subject, $user)),
            self::VIEW_ALBUM => $isOrganizer || ($user instanceof User && $this->isAcceptedParticipant($subject, $user)),
            default => false,
        };
    }

    private function canView(PrivateActivity $activity, mixed $user, bool $isOrganizer): bool
    {
        if ($isOrganizer) {
            return true;
        }

        return match ($activity->getVisibility()) {
            PrivateActivityVisibility::Public => true,
            PrivateActivityVisibility::MembersOnly => $user instanceof User,
            PrivateActivityVisibility::Private => $user instanceof User && (
                $this->hasAnyParticipation($activity, $user) || $this->hasAnyInvitation($activity, $user)
            ),
        };
    }

    private function isAcceptedParticipant(PrivateActivity $activity, User $user): bool
    {
        $participation = $this->participations->findOneByActivityAndParticipant($activity, $user);

        return null !== $participation && ParticipationStatus::Accepted === $participation->getStatus();
    }

    private function hasAnyParticipation(PrivateActivity $activity, User $user): bool
    {
        return null !== $this->participations->findOneByActivityAndParticipant($activity, $user);
    }

    private function hasAnyInvitation(PrivateActivity $activity, User $user): bool
    {
        foreach ($activity->getInvitations() as $invitation) {
            if ($invitation->getInvitee() === $user) {
                return true;
            }
        }

        return false;
    }
}
