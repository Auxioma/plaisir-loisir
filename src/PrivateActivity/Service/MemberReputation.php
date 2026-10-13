<?php

declare(strict_types=1);

namespace App\PrivateActivity\Service;

use App\Messaging\Repository\ConversationRepository;
use App\PrivateActivity\Entity\MemberReview;
use App\PrivateActivity\Entity\Participation;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\ParticipationStatus;
use App\PrivateActivity\Enum\PrivateActivityStatus;
use App\PrivateActivity\Repository\MemberReviewRepository;
use App\PrivateActivity\Repository\ParticipationRepository;
use App\PrivateActivity\Repository\PrivateActivityRepository;
use App\User\Entity\User;
use App\User\Enum\UserStatus;

/**
 * Réputation d'un membre (13/10, maquette « detail-profil ») : niveau,
 * badges, statistiques et note, calculés à partir de son activité réelle sur
 * la plateforme — rien n'est saisi à la main.
 *
 * Niveau : points = 3 par activité organisée et menée à terme, 1 par
 * participation honorée, 1 par avis reçu. Le niveau « Ambassadeur » exige en
 * plus une note moyenne d'au moins 4,5 sur 5 avis minimum.
 */
final class MemberReputation
{
    /** Niveaux : [libellé, points minimum, icône]. */
    public const LEVELS = [
        1 => ['Débutant', 0, 'leaf'],
        2 => ['Explorateur', 3, 'footprints'],
        3 => ['Actif', 10, 'users'],
        4 => ['Confirmé', 25, 'shield_check'],
        5 => ['Ambassadeur', 50, 'crown'],
    ];

    /** @var array<string, array<string, mixed>> */
    private array $cache = [];

    public function __construct(
        private readonly PrivateActivityRepository $activities,
        private readonly ParticipationRepository $participations,
        private readonly MemberReviewRepository $reviews,
        private readonly ConversationRepository $conversations,
    ) {
    }

    /**
     * @return array{
     *     verified: bool, member_since: ?\DateTimeImmutable,
     *     organized: int, organized_this_year: int, done: int,
     *     honored_rate: ?int, cancellations_12m: int, response_hours: ?float,
     *     rating: ?float, reviews_count: int, positive_rate: ?int, criteria: array<string, ?float>,
     *     points: int, level: int, level_label: string, next_level: ?int, next_label: ?string, progress: int,
     *     badges: list<array{key: string, label: string, icon: string, tone: string}>
     * }
     */
    public function of(User $user): array
    {
        $key = (string) $user->getId();
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $now = new \DateTimeImmutable();
        $yearAgo = $now->modify('-12 months');

        // Activités organisées (hors brouillons).
        $organizedAll = array_filter(
            $this->activities->findByOrganizer($user),
            static fn (PrivateActivity $a): bool => !$a->getStatus()->isUnpublished(),
        );
        $organized = array_filter($organizedAll, static fn (PrivateActivity $a): bool => PrivateActivityStatus::Cancelled !== $a->getStatus());
        $organizedDone = array_filter($organized, static fn (PrivateActivity $a): bool => null !== $a->getScheduledAt() && $a->getScheduledAt() < $now);
        $thisYear = array_filter($organized, static fn (PrivateActivity $a): bool => null !== $a->getScheduledAt() && (int) $a->getScheduledAt()->format('Y') === (int) $now->format('Y'));
        $organizerCancelled = array_filter($organizedAll, static fn (PrivateActivity $a): bool => PrivateActivityStatus::Cancelled === $a->getStatus() && ($a->getUpdatedAt() ?? $now) >= $yearAgo);

        // Participations à des activités d'autres membres.
        $mine = array_filter(
            $this->participations->findByParticipant($user),
            static fn (Participation $p): bool => $p->getPrivateActivity()?->getOrganizer() !== $user,
        );
        $pastActivity = static fn (Participation $p): bool => null !== $p->getPrivateActivity()?->getScheduledAt() && $p->getPrivateActivity()->getScheduledAt() < $now;
        $honored = array_filter($mine, static fn (Participation $p): bool => ParticipationStatus::Accepted === $p->getStatus() && $pastActivity($p));
        $cancelled = array_filter($mine, static fn (Participation $p): bool => ParticipationStatus::Cancelled === $p->getStatus());
        $cancelled12 = array_filter($cancelled, static fn (Participation $p): bool => ($p->getUpdatedAt() ?? $now) >= $yearAgo);
        $commitments = \count($honored) + \count($cancelled);

        // Avis reçus en tant qu'organisateur.
        $reviews = $this->reviews->findForOrganizer($user);
        $count = \count($reviews);
        $avg = static fn (callable $f): ?float => $count > 0 ? round(array_sum(array_map($f, $reviews)) / $count, 1) : null;
        $criteria = [];
        foreach (array_keys(MemberReview::CRITERIA) as $c) {
            $criteria[$c] = $avg(static fn (MemberReview $r): int => $r->getCriterion($c));
        }
        $rating = $avg(static fn (MemberReview $r): int => $r->getRating());
        $positive = $count > 0 ? (int) round(100 * \count(array_filter($reviews, static fn (MemberReview $r): bool => $r->getRating() >= 4)) / $count) : null;

        // Niveau.
        $points = 3 * \count($organizedDone) + \count($honored) + $count;
        $level = 1;
        foreach (self::LEVELS as $n => [, $min]) {
            if ($points >= $min) {
                $level = $n;
            }
        }
        if (5 === $level && (null === $rating || $rating < 4.5 || $count < 5)) {
            $level = 4;
        }
        $next = $level < 5 ? $level + 1 : null;
        $progress = null === $next ? 100 : (int) min(100, round(100 * ($points - self::LEVELS[$level][1]) / max(1, self::LEVELS[$next][1] - self::LEVELS[$level][1])));

        // Badges.
        $badges = [];
        if (5 === $level) {
            $badges[] = ['key' => 'ambassador', 'label' => 'Ambassadeur', 'icon' => 'crown', 'tone' => 'orange'];
        }
        if (\count($organizedDone) >= 3 && \count($organizerCancelled) <= 1) {
            $badges[] = ['key' => 'reliable', 'label' => 'Organisateur fiable', 'icon' => 'calendar', 'tone' => 'violet'];
        }
        if (null !== $criteria['punctuality'] && $criteria['punctuality'] >= 4.5 && $count >= 3) {
            $badges[] = ['key' => 'punctual', 'label' => 'Toujours ponctuel', 'icon' => 'clock', 'tone' => 'blue'];
        }
        if (\count($honored) >= 5) {
            $badges[] = ['key' => 'active', 'label' => 'Membre actif', 'icon' => 'users', 'tone' => 'green'];
        }
        foreach ([50, 25, 10, 5] as $milestone) {
            if (\count($organizedDone) >= $milestone) {
                $badges[] = ['key' => 'organized', 'label' => \sprintf('%d activités organisées', $milestone), 'icon' => 'star', 'tone' => 'red'];
                break;
            }
        }

        return $this->cache[$key] = [
            'verified' => UserStatus::Active === $user->getStatus(),
            'member_since' => $user->getCreatedAt(),
            'organized' => \count($organized),
            'organized_this_year' => \count($thisYear),
            'done' => \count($honored) + \count($organizedDone),
            'honored_rate' => $commitments > 0 ? (int) round(100 * \count($honored) / $commitments) : null,
            'cancellations_12m' => \count($cancelled12) + \count($organizerCancelled),
            'response_hours' => $this->responseHours($user),
            'rating' => $rating,
            'reviews_count' => $count,
            'positive_rate' => $positive,
            'criteria' => $criteria,
            'points' => $points,
            'level' => $level,
            'level_label' => self::LEVELS[$level][0],
            'next_level' => $next,
            'next_label' => null !== $next ? self::LEVELS[$next][0] : null,
            'progress' => $progress,
            'badges' => $badges,
        ];
    }

    /**
     * Délai médian de réponse du membre aux messages reçus, en heures ; null
     * sans au moins trois réponses mesurables.
     */
    private function responseHours(User $user): ?float
    {
        $delays = [];
        foreach ($this->conversations->findForUser($user) as $conversation) {
            $waitingSince = null;
            foreach ($conversation->getMessages() as $message) {
                $at = $message->getCreatedAt();
                if (null === $at) {
                    continue;
                }
                if ($message->getAuthor() !== $user) {
                    $waitingSince ??= $at;
                } elseif (null !== $waitingSince) {
                    $delays[] = ($at->getTimestamp() - $waitingSince->getTimestamp()) / 3600;
                    $waitingSince = null;
                }
            }
        }
        if (\count($delays) < 3) {
            return null;
        }
        sort($delays);

        return round($delays[intdiv(\count($delays), 2)], 1);
    }
}
