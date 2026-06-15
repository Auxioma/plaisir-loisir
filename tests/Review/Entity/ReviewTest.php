<?php

declare(strict_types=1);

namespace App\Tests\Review\Entity;

use App\Booking\Entity\Booking;
use App\Catalog\Entity\Service;
use App\Review\Entity\Review;
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
}
