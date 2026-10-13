<?php

declare(strict_types=1);

namespace App\PrivateActivity\Service;

use App\Notification\Enum\NotificationCategory;
use App\Notification\Service\NotificationService;
use App\PrivateActivity\Entity\MemberReview;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\ParticipationStatus;
use App\PrivateActivity\Repository\MemberReviewRepository;
use App\PrivateActivity\Repository\ParticipationRepository;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Avis d'un participant sur l'organisateur (13/10). Règles : place acceptée,
 * activité passée, pas son propre événement, un seul avis par activité.
 */
final class MemberReviewService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ParticipationRepository $participations,
        private readonly MemberReviewRepository $reviews,
        private readonly NotificationService $notifications,
    ) {
    }

    public function canReview(PrivateActivity $activity, ?User $user): bool
    {
        if (null === $user || $activity->getOrganizer() === $user) {
            return false;
        }
        $when = $activity->getEndsAt() ?? $activity->getScheduledAt();
        if (null === $when || $when > new \DateTimeImmutable()) {
            return false;
        }
        $participation = $this->participations->findOneByActivityAndParticipant($activity, $user);

        return null !== $participation
            && ParticipationStatus::Accepted === $participation->getStatus()
            && null === $this->reviews->findOneByActivityAndAuthor($activity, $user);
    }

    /**
     * @param array<string, int> $criteria
     *
     * @throws \InvalidArgumentException si l'avis n'est pas permis
     */
    public function submit(PrivateActivity $activity, User $author, array $criteria, ?string $comment): MemberReview
    {
        if (!$this->canReview($activity, $author)) {
            throw new \InvalidArgumentException('Vous ne pouvez pas (ou plus) donner d’avis sur cette activité.');
        }

        $review = (new MemberReview())
            ->setPrivateActivity($activity)
            ->setAuthor($author)
            ->rate($criteria)
            ->setComment($comment);
        $this->entityManager->persist($review);
        $this->entityManager->flush();

        if (null !== ($organizer = $activity->getOrganizer())) {
            $this->notifications->notify($organizer, NotificationCategory::Activity, 'Nouvel avis reçu', \sprintf('%s a laissé un avis (%d/5) sur « %s ».', $author->getFirstName(), $review->getRating(), $activity->getTitle()));
        }

        return $review;
    }
}
