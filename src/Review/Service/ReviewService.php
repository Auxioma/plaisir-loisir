<?php

declare(strict_types=1);

namespace App\Review\Service;

use App\Booking\Entity\Booking;
use App\Booking\Enum\BookingStatus;
use App\Quote\Entity\Quote;
use App\Quote\Enum\QuoteStatus;
use App\Review\Entity\Review;
use App\Review\Event\ReviewAdded;
use App\Review\Repository\ReviewRepository;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Logique métier des avis. Adosse chaque avis à un devis accepté pour limiter
 * les faux avis (on ne note que le professionnel avec qui on a réellement été
 * mis en relation, §16.2 du CDC).
 */
final class ReviewService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReviewRepository $reviews,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws \InvalidArgumentException si la note est hors bornes, si l'auteur n'est
     *                                   pas le client de la demande, si le devis n'est
     *                                   pas accepté, ou s'il a déjà un avis
     */
    public function addReview(Quote $quote, User $author, int $rating, ?string $comment = null): Review
    {
        if ($rating < 1 || $rating > 5) {
            throw new \InvalidArgumentException('La note doit être comprise entre 1 et 5.');
        }

        if (QuoteStatus::Accepted !== $quote->getStatus()) {
            throw new \InvalidArgumentException('Seul un devis accepté peut être noté.');
        }

        if ($quote->getServiceRequest()?->getClient() !== $author) {
            throw new \InvalidArgumentException('Seul le client à l\'origine de la demande peut noter ce devis.');
        }

        if (null !== $this->reviews->findOneByQuote($quote)) {
            throw new \InvalidArgumentException('Ce devis a déjà reçu un avis.');
        }

        $review = (new Review())
            ->setAuthor($author)
            ->setProvider($quote->getProvider())
            ->setQuote($quote)
            ->setRating($rating)
            ->setComment($comment);

        $this->entityManager->persist($review);
        $this->entityManager->flush();

        // Émet un événement de domaine : les abonnés (ex. notification de l'annonceur) réagissent.
        $this->eventDispatcher->dispatch(new ReviewAdded($review));

        return $review;
    }

    /**
     * Avis sur une activité réservée (catalogue), une fois la séance terminée.
     *
     * @throws \InvalidArgumentException note hors bornes, réservation d'un autre
     *                                   client, non terminée, ou déjà notée
     */
    public function addBookingReview(Booking $booking, User $author, int $rating, ?string $comment = null): Review
    {
        if ($rating < 1 || $rating > 5) {
            throw new \InvalidArgumentException('La note doit être comprise entre 1 et 5.');
        }

        if ($booking->getClient() !== $author) {
            throw new \InvalidArgumentException('Seul le client de la réservation peut la noter.');
        }

        if (BookingStatus::Completed !== $booking->getStatus()) {
            throw new \InvalidArgumentException('Vous pourrez noter cette activité une fois la séance terminée.');
        }

        if (null !== $this->reviews->findOneBy(['booking' => $booking])) {
            throw new \InvalidArgumentException('Cette réservation a déjà reçu un avis.');
        }

        $service = $booking->getService();
        $review = (new Review())
            ->setAuthor($author)
            ->setProvider($service?->getProvider())
            ->setBooking($booking)
            ->setService($service)
            ->setRating($rating)
            ->setComment($comment);

        $this->entityManager->persist($review);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new ReviewAdded($review));

        return $review;
    }
}
