<?php

declare(strict_types=1);

namespace App\Tests\Review\Entity;

use App\Provider\Entity\ProviderProfile;
use App\Quote\Entity\Quote;
use App\Review\Entity\Review;
use App\Review\Enum\ReviewStatus;
use App\User\Entity\User;
use PHPUnit\Framework\TestCase;

final class ReviewTest extends TestCase
{
    public function testFieldsAreAssignable(): void
    {
        $author = new User();
        $provider = new ProviderProfile();
        $quote = new Quote();

        $review = (new Review())
            ->setAuthor($author)
            ->setProvider($provider)
            ->setQuote($quote)
            ->setRating(4)
            ->setComment('Super expérience !');

        self::assertSame($author, $review->getAuthor());
        self::assertSame($provider, $review->getProvider());
        self::assertSame($quote, $review->getQuote());
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
