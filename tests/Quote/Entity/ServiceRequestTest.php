<?php

declare(strict_types=1);

namespace App\Tests\Quote\Entity;

use App\Quote\Entity\Quote;
use App\Quote\Entity\ServiceRequest;
use App\Quote\Enum\ServiceRequestStatus;
use PHPUnit\Framework\TestCase;

final class ServiceRequestTest extends TestCase
{
    public function testDefaultOpenAndClose(): void
    {
        $request = new ServiceRequest();

        self::assertSame(ServiceRequestStatus::Open, $request->getStatus());
        self::assertTrue($request->isOpen());

        $request->close();

        self::assertFalse($request->isOpen());
        self::assertSame(ServiceRequestStatus::Closed, $request->getStatus());
    }

    public function testAddQuoteLinksBothSidesAndAvoidsDuplicates(): void
    {
        $request = new ServiceRequest();
        $quote = new Quote();

        $request->addQuote($quote);
        $request->addQuote($quote); // doublon ignoré

        self::assertCount(1, $request->getQuotes());
        self::assertSame($request, $quote->getServiceRequest());
    }
}
