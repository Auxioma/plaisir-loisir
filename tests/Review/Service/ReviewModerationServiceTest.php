<?php

declare(strict_types=1);

namespace App\Tests\Review\Service;

use App\Catalog\Entity\Service;
use App\Provider\Entity\ProviderProfile;
use App\Review\Entity\Review;
use App\Review\Enum\ReviewStatus;
use App\Review\Service\ReviewModerationService;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ReviewModerationServiceTest extends TestCase
{
    public function testApproveSetsPublished(): void
    {
        $review = new Review();
        $review->reject(); // on part d'un avis rejeté

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        (new ReviewModerationService($em))->approve($review);

        self::assertSame(ReviewStatus::Published, $review->getStatus());
    }

    public function testRejectSetsRejected(): void
    {
        $review = new Review();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        (new ReviewModerationService($em))->reject($review);

        self::assertSame(ReviewStatus::Rejected, $review->getStatus());
    }

    public function testReplyByProviderOwner(): void
    {
        $owner = new User();
        $service = (new Service())->setProvider((new ProviderProfile())->setUser($owner));
        $review = (new Review())->setService($service)->setRating(4);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        (new ReviewModerationService($em))->reply($review, $owner, 'Merci de votre visite !');

        self::assertSame('Merci de votre visite !', $review->getProviderReply());
    }

    public function testReplyRejectsNonOwner(): void
    {
        $service = (new Service())->setProvider((new ProviderProfile())->setUser(new User()));
        $review = (new Review())->setService($service)->setRating(4);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $this->expectException(\InvalidArgumentException::class);

        (new ReviewModerationService($em))->reply($review, new User(), 'Pas mon activité');
    }
}
