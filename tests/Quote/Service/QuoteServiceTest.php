<?php

declare(strict_types=1);

namespace App\Tests\Quote\Service;

use App\Catalog\Entity\Category;
use App\Provider\Entity\ProviderProfile;
use App\Quote\Entity\Quote;
use App\Quote\Entity\ServiceRequest;
use App\Quote\Enum\QuoteStatus;
use App\Quote\Enum\ServiceRequestStatus;
use App\Quote\Repository\QuoteRepository;
use App\Quote\Service\QuoteService;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class QuoteServiceTest extends TestCase
{
    public function testCreateRequestPersists(): void
    {
        $client = new User();
        $category = new Category();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(ServiceRequest::class));
        $em->expects(self::once())->method('flush');

        $request = (new QuoteService($em, $this->createStub(QuoteRepository::class)))
            ->createRequest($client, $category, 'Photographe mariage', 'Besoin d\'un photographe pour la journée.');

        self::assertSame($client, $request->getClient());
        self::assertSame($category, $request->getCategory());
        self::assertTrue($request->isOpen());
    }

    public function testSubmitQuoteAddsWhenOpenAndNoDuplicate(): void
    {
        $request = new ServiceRequest();
        $provider = new ProviderProfile();

        $quotes = $this->createStub(QuoteRepository::class);
        $quotes->method('findOneByRequestAndProvider')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(Quote::class));
        $em->expects(self::once())->method('flush');

        $quote = (new QuoteService($em, $quotes))->submitQuote($request, $provider, '950.00', 'Reportage complet');

        self::assertSame($provider, $quote->getProvider());
        self::assertSame('950.00', $quote->getAmount());
        self::assertCount(1, $request->getQuotes());
    }

    public function testSubmitQuoteRejectsClosedRequest(): void
    {
        $request = new ServiceRequest();
        $request->close();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);

        (new QuoteService($em, $this->createStub(QuoteRepository::class)))
            ->submitQuote($request, new ProviderProfile(), '100.00');
    }

    public function testSubmitQuoteRejectsDuplicateProvider(): void
    {
        $request = new ServiceRequest();

        $quotes = $this->createStub(QuoteRepository::class);
        $quotes->method('findOneByRequestAndProvider')->willReturn(new Quote());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);

        (new QuoteService($em, $quotes))->submitQuote($request, new ProviderProfile(), '100.00');
    }

    public function testAcceptDeclinesOthersAndClosesRequest(): void
    {
        $request = new ServiceRequest();
        $chosen = (new Quote())->setProvider(new ProviderProfile());
        $other = (new Quote())->setProvider(new ProviderProfile());
        $request->addQuote($chosen)->addQuote($other);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        (new QuoteService($em, $this->createStub(QuoteRepository::class)))->accept($chosen);

        self::assertSame(QuoteStatus::Accepted, $chosen->getStatus());
        self::assertSame(QuoteStatus::Declined, $other->getStatus());
        self::assertSame(ServiceRequestStatus::Closed, $request->getStatus());
    }
}
