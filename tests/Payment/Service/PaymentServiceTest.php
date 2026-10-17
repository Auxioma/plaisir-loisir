<?php

declare(strict_types=1);

namespace App\Tests\Payment\Service;

use App\Booking\Entity\Booking;
use App\Booking\Enum\BookingStatus;
use App\Payment\Entity\Payment;
use App\Payment\Enum\PaymentStatus;
use App\Payment\Repository\PaymentRepository;
use App\Payment\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Règlement direct au professionnel (17/10) : la demande crée un règlement
 * « à régler » sans rien débiter ; le professionnel le déclare reçu.
 */
final class PaymentServiceTest extends TestCase
{
    private function pendingBooking(): Booking
    {
        return (new Booking())->setTotalPrice('149.70')->setCurrency('EUR');
    }

    public function testRequestCreatesAnUnpaidSettlementWithoutCharging(): void
    {
        $booking = $this->pendingBooking();
        $payments = $this->createStub(PaymentRepository::class);
        $payments->method('findOneByBooking')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(Payment::class));
        $em->expects(self::once())->method('flush');

        $payment = (new PaymentService($em, $payments))->request($booking);

        self::assertSame(PaymentStatus::Pending, $payment->getStatus());
        self::assertSame('149.70', $payment->getAmount());
        self::assertNull($payment->getMethod());
        self::assertSame(BookingStatus::Pending, $booking->getStatus(), 'C’est le professionnel qui confirme la réservation.');
    }

    public function testRequestCannotBeSentTwice(): void
    {
        $payments = $this->createStub(PaymentRepository::class);
        $payments->method('findOneByBooking')->willReturn(new Payment());

        $this->expectException(\InvalidArgumentException::class);
        (new PaymentService($this->createStub(EntityManagerInterface::class), $payments))->request($this->pendingBooking());
    }

    public function testRequestRejectsANonPendingBooking(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PaymentService($this->createStub(EntityManagerInterface::class), $this->createStub(PaymentRepository::class)))
            ->request($this->pendingBooking()->setStatus(BookingStatus::Cancelled));
    }

    public function testMarkPaidRecordsTheMethodAndDate(): void
    {
        $booking = $this->pendingBooking()->setStatus(BookingStatus::Confirmed);
        $payment = (new Payment())->setBooking($booking)->setAmount('149.70');
        $payments = $this->createStub(PaymentRepository::class);
        $payments->method('findOneByBooking')->willReturn($payment);

        (new PaymentService($this->createStub(EntityManagerInterface::class), $payments))->markPaid($booking, 'transfer');

        self::assertSame(PaymentStatus::Paid, $payment->getStatus());
        self::assertSame('transfer', $payment->getMethod());
        self::assertNotNull($payment->getPaidAt());
    }

    public function testMarkPaidCreatesTheSettlementWhenMissing(): void
    {
        $payments = $this->createStub(PaymentRepository::class);
        $payments->method('findOneByBooking')->willReturn(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(Payment::class));

        $payment = (new PaymentService($em, $payments))->markPaid($this->pendingBooking(), 'onsite');

        self::assertSame(PaymentStatus::Paid, $payment->getStatus());
        self::assertSame('149.70', $payment->getAmount());
    }

    public function testMarkPaidRejectsAnUnknownMethodOrACancelledBooking(): void
    {
        $service = new PaymentService($this->createStub(EntityManagerInterface::class), $this->createStub(PaymentRepository::class));

        try {
            $service->markPaid($this->pendingBooking(), 'bitcoin');
            self::fail('Moyen inconnu accepté.');
        } catch (\InvalidArgumentException) {
        }

        $this->expectException(\InvalidArgumentException::class);
        $service->markPaid($this->pendingBooking()->setStatus(BookingStatus::Cancelled), 'onsite');
    }
}
