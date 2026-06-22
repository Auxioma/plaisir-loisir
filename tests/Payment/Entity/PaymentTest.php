<?php

declare(strict_types=1);

namespace App\Tests\Payment\Entity;

use App\Booking\Entity\Booking;
use App\Payment\Entity\Payment;
use App\Payment\Enum\PaymentStatus;
use PHPUnit\Framework\TestCase;

final class PaymentTest extends TestCase
{
    public function testDefaults(): void
    {
        $payment = new Payment();

        self::assertSame(PaymentStatus::Pending, $payment->getStatus());
        self::assertSame('EUR', $payment->getCurrency());
        self::assertNull($payment->getReference());
    }

    public function testFieldsAreAssignable(): void
    {
        $booking = new Booking();

        $payment = (new Payment())
            ->setBooking($booking)
            ->setAmount('149.70')
            ->setStatus(PaymentStatus::Paid)
            ->setReference('tx_123');

        self::assertSame($booking, $payment->getBooking());
        self::assertSame('149.70', $payment->getAmount());
        self::assertSame(PaymentStatus::Paid, $payment->getStatus());
        self::assertSame('tx_123', $payment->getReference());
    }
}
