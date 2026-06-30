<?php

declare(strict_types=1);

namespace App\Tests\Review\Entity;

use App\Booking\Entity\Booking;
use App\Catalog\Entity\Service;
use App\Review\Entity\Review;
use App\Review\Enum\ReviewStatus;
use App\User\Entity\User;
use PHPUnit\Framework\TestCase;

final class ReviewTest extends TestCase
{
    public function testFieldsAreAssignable(): void
    {
        $author = new User();
        $service = new Service();
        $booking = new Booking();

        $review = (new Review())
            ->setAuthor($author)
            ->setService($service)
            ->setBooking($booking)
            ->setRating(4)
            ->setComment('Super expérience !');

        self::assertSame($author, $review->getAuthor());
        self::assertSame($service, $review->getService());
        self::assertSame($booking, $review->getBooking());
        self::assertSame(4, $review->getRating());
        self::assertSame('Super expérience !', $review->getComment());
    }

    public function testDefaultStatusIsPublishedAndNoReply(): void
    {
        $review = new Review();

        self::assertSame(ReviewStatus::Published, $review->getStatus());
        self::assertNull($review->getProviderReply());
        self::assertNull($review->getRepliedAt());
    }

    public function testApproveRejectAndReply(): void
    {
        $review = new Review();

        $review->reject();
        self::assertSame(ReviewStatus::Rejected, $review->getStatus());

        $review->approve();
        self::assertSame(ReviewStatus::Published, $review->getStatus());

        $review->reply('Merci pour votre retour !');
        self::assertSame('Merci pour votre retour !', $review->getProviderReply());
        self::assertInstanceOf(\DateTimeImmutable::class, $review->getRepliedAt());
    }
}
