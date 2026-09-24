<?php

declare(strict_types=1);

namespace App\Tests\Review\Service;

use App\Provider\Entity\ProviderProfile;
use App\Quote\Entity\Quote;
use App\Quote\Entity\ServiceRequest;
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
    private function acceptedQuote(User $client): Quote
    {
        $request = (new ServiceRequest())->setClient($client);
        $quote = (new Quote())->setServiceRequest($request)->setProvider(new ProviderProfile())->setAmount('100.00');
        $quote->accept();

        return $quote;
    }

    public function testAddReviewCreatesReviewFromAcceptedQuote(): void
    {
        $client = new User();
        $quote = $this->acceptedQuote($client);

        $reviews = $this->createStub(ReviewRepository::class);
        $reviews->method('findOneByQuote')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(Review::class));
        $em->expects(self::once())->method('flush');

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::once())->method('dispatch')
            ->with(self::isInstanceOf(ReviewAdded::class))
            ->willReturnArgument(0);

        $review = (new ReviewService($em, $reviews, $dispatcher))->addReview($quote, $client, 5, 'Génial');

        self::assertSame($client, $review->getAuthor());
        self::assertSame($quote->getProvider(), $review->getProvider());
        self::assertSame($quote, $review->getQuote());
        self::assertSame(5, $review->getRating());
        self::assertSame('Génial', $review->getComment());
    }

    public function testAddReviewRejectsRatingOutOfRange(): void
    {
        $client = new User();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);

        (new ReviewService($em, $this->createStub(ReviewRepository::class), $this->createStub(EventDispatcherInterface::class)))
            ->addReview($this->acceptedQuote($client), $client, 6);
    }

    public function testAddReviewRejectsQuoteNotAccepted(): void
    {
        $client = new User();
        $request = (new ServiceRequest())->setClient($client);
        $quote = (new Quote())->setServiceRequest($request)->setProvider(new ProviderProfile())->setAmount('100.00');
        // Statut par défaut : en attente, pas encore accepté.

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);

        (new ReviewService($em, $this->createStub(ReviewRepository::class), $this->createStub(EventDispatcherInterface::class)))
            ->addReview($quote, $client, 4);
    }

    public function testAddReviewRejectsAnotherClient(): void
    {
        $owner = new User();
        $intrus = new User();
        $quote = $this->acceptedQuote($owner);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);

        (new ReviewService($em, $this->createStub(ReviewRepository::class), $this->createStub(EventDispatcherInterface::class)))
            ->addReview($quote, $intrus, 4);
    }

    public function testAddReviewRejectsAlreadyReviewedQuote(): void
    {
        $client = new User();
        $quote = $this->acceptedQuote($client);

        $reviews = $this->createStub(ReviewRepository::class);
        $reviews->method('findOneByQuote')->willReturn(new Review());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);

        (new ReviewService($em, $reviews, $this->createStub(EventDispatcherInterface::class)))
            ->addReview($quote, $client, 4);
    }
}
