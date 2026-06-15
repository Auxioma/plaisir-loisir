<?php

declare(strict_types=1);

namespace App\Review\Service;

use App\Booking\Entity\Booking;
use App\Booking\Enum\BookingStatus;
use App\Review\Entity\Review;
use App\Review\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Logique métier des avis. Adosse chaque avis à une réservation terminée pour
 * limiter les faux avis (on ne note que ce qu'on a réellement réservé et vécu).
 */
final class ReviewService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReviewRepository $reviews,
    ) {
    }

    /**
     * @throws \InvalidArgumentException si la note est hors bornes, si la réservation
     *                                   n'est pas terminée, ou si elle a déjà un avis
     */
    public function addReview(Booking $booking, int $rating, ?string $comment = null): Review
    {
        if ($rating < 1 || $rating > 5) {
            throw new \InvalidArgumentException('La note doit être comprise entre 1 et 5.');
        }

        if (BookingStatus::Completed !== $booking->getStatus()) {
            throw new \InvalidArgumentException('Seule une réservation terminée peut être notée.');
        }

        if (null !== $this->reviews->findOneByBooking($booking)) {
            throw new \InvalidArgumentException('Cette réservation a déjà reçu un avis.');
        }

        $review = (new Review())
            ->setAuthor($booking->getClient())
            ->setService($booking->getService())
            ->setBooking($booking)
            ->setRating($rating)
            ->setComment($comment);

        $this->entityManager->persist($review);
        $this->entityManager->flush();

        return $review;
    }
}
