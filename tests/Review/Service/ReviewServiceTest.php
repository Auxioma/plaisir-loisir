<?php

declare(strict_types=1);

namespace App\Tests\Review\Service;

use App\Booking\Entity\Booking;
use App\Booking\Enum\BookingStatus;
use App\Catalog\Entity\Service;
use App\Review\Entity\Review;
use App\Review\Event\ReviewAdded;
use App\Review\Repository\ReviewRepository;
use App\Review\Service\ReviewService;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class ReviewServiceTest extends TestCase
{
    private function completedBooking(): Booking
    {
        return (new Booking())
            ->setClient(new User())
            ->setService(new Service())
            ->setStatus(BookingStatus::Completed);
    }

    public function testAddReviewCreatesReviewFromBooking(): void
    {
        $booking = $this->completedBooking();

        $reviews = $this->createStub(ReviewRepository::class);
        $reviews->method('findOneByBooking')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(Review::class));
        $em->expects(self::once())->method('flush');

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::once())->method('dispatch')
            ->with(self::isInstanceOf(ReviewAdded::class))
            ->willReturnArgument(0);

        $review = (new ReviewService($em, $reviews, $dispatcher))->addReview($booking, 5, 'Génial');

        self::assertSame($booking->getClient(), $review->getAuthor());
        self::assertSame($booking->getService(), $review->getService());
        self::assertSame($booking, $review->getBooking());
        self::assertSame(5, $review->getRating());
        self::assertSame('Génial', $review->getComment());
    }

    public function testAddReviewRejectsRatingOutOfRange(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);

        (new ReviewService($em, $this->createStub(ReviewRepository::class), $this->createStub(EventDispatcherInterface::class)))
            ->addReview($this->completedBooking(), 6);
    }

    public function testAddReviewRejectsBookingNotCompleted(): void
    {
        // Réservation au statut pending par défaut.
        $booking = (new Booking())->setClient(new User())->setService(new Service());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);

        (new ReviewService($em, $this->createStub(ReviewRepository::class), $this->createStub(EventDispatcherInterface::class)))
            ->addReview($booking, 4);
    }

    public function testAddReviewRejectsAlreadyReviewedBooking(): void
    {
        $reviews = $this->createStub(ReviewRepository::class);
        $reviews->method('findOneByBooking')->willReturn(new Review());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);

        (new ReviewService($em, $reviews, $this->createStub(EventDispatcherInterface::class)))
            ->addReview($this->completedBooking(), 4);
    }
}
