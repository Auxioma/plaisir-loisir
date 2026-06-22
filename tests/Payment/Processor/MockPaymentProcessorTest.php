<?php

declare(strict_types=1);

namespace App\Tests\Payment\Processor;

use App\Payment\Entity\Payment;
use App\Payment\Processor\MockPaymentProcessor;
use PHPUnit\Framework\TestCase;

final class MockPaymentProcessorTest extends TestCase
{
    public function testChargeReturnsATransactionReference(): void
    {
        $reference = (new MockPaymentProcessor())->charge((new Payment())->setAmount('49.90'));

        self::assertNotNull($reference);
        self::assertStringStartsWith('mock_', $reference);
    }
}
