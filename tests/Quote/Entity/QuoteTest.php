<?php

declare(strict_types=1);

namespace App\Tests\Quote\Entity;

use App\Quote\Entity\Quote;
use App\Quote\Enum\QuoteStatus;
use PHPUnit\Framework\TestCase;

final class QuoteTest extends TestCase
{
    public function testDefaultStatusIsPending(): void
    {
        self::assertSame(QuoteStatus::Pending, (new Quote())->getStatus());
    }

    public function testAcceptAndDecline(): void
    {
        $quote = new Quote();

        $quote->accept();
        self::assertSame(QuoteStatus::Accepted, $quote->getStatus());

        $quote->decline();
        self::assertSame(QuoteStatus::Declined, $quote->getStatus());
    }
}
