<?php

declare(strict_types=1);

namespace App\Tests\Payment\Service;

use App\Booking\Entity\Booking;
use App\Booking\Enum\BookingStatus;
use App\Payment\Entity\Payment;
use App\Payment\Enum\PaymentStatus;
use App\Payment\Processor\PaymentProcessor;
use App\Payment\Repository\PaymentRepository;
use App\Payment\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\WorkflowInterface;

final class PaymentServiceTest extends TestCase
{
    private function pendingBooking(): Booking
    {
        // Statut pending par défaut.
        return (new Booking())->setTotalPrice('149.70')->setCurrency('EUR');
    }

    public function testPaySuccessMarksPaidAndConfirmsBooking(): void
    {
        $booking = $this->pendingBooking();

        $processor = $this->createStub(PaymentProcessor::class);
        $processor->method('charge')->willReturn('tx_ok');

        $payments = $this->createStub(PaymentRepository::class);
        $payments->method('findOneByBooking')->willReturn(null);

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->expects(self::once())->method('apply')->with($booking, 'confirm')->willReturn(new Marking());

        $registry = $this->createMock(Registry::class);
        $registry->expects(self::once())->method('get')->with($booking, 'booking')->willReturn($workflow);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(Payment::class));
        $em->expects(self::once())->method('flush');

        $payment = (new PaymentService($em, $processor, $payments, $registry))->pay($booking);

        self::assertSame(PaymentStatus::Paid, $payment->getStatus());
        self::assertSame('tx_ok', $payment->getReference());
        self::assertSame('149.70', $payment->getAmount());
        self::assertSame($booking, $payment->getBooking());
    }

    public function testPayFailureMarksFailedAndDoesNotConfirm(): void
    {
        $booking = $this->pendingBooking();

        $processor = $this->createStub(PaymentProcessor::class);
        $processor->method('charge')->willReturn(null);

        $payments = $this->createStub(PaymentRepository::class);
        $payments->method('findOneByBooking')->willReturn(null);

        // Aucune confirmation de réservation en cas d'échec.
        $registry = $this->createMock(Registry::class);
        $registry->expects(self::never())->method('get');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $payment = (new PaymentService($em, $processor, $payments, $registry))->pay($booking);

        self::assertSame(PaymentStatus::Failed, $payment->getStatus());
        self::assertNull($payment->getReference());
    }

    public function testPayRejectsNonPendingBooking(): void
    {
        $booking = (new Booking())->setTotalPrice('10.00')->setStatus(BookingStatus::Confirmed);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);

        (new PaymentService(
            $em,
            $this->createStub(PaymentProcessor::class),
            $this->createStub(PaymentRepository::class),
            $this->createStub(Registry::class),
        ))->pay($booking);
    }

    public function testPayRejectsAlreadyPaidBooking(): void
    {
        $booking = $this->pendingBooking();

        $payments = $this->createStub(PaymentRepository::class);
        $payments->method('findOneByBooking')->willReturn(new Payment());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);

        (new PaymentService(
            $em,
            $this->createStub(PaymentProcessor::class),
            $payments,
            $this->createStub(Registry::class),
        ))->pay($booking);
    }

    public function testRefundMarksRefundedAndTransitionsBooking(): void
    {
        $booking = (new Booking())->setStatus(BookingStatus::Confirmed)->setTotalPrice('10.00');
        $payment = (new Payment())->setBooking($booking)->setAmount('10.00')->setStatus(PaymentStatus::Paid);

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->expects(self::once())->method('can')->with($booking, 'refund')->willReturn(true);
        $workflow->expects(self::once())->method('apply')->with($booking, 'refund')->willReturn(new Marking());

        $registry = $this->createMock(Registry::class);
        $registry->expects(self::once())->method('get')->with($booking, 'booking')->willReturn($workflow);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        (new PaymentService($em, $this->createStub(PaymentProcessor::class), $this->createStub(PaymentRepository::class), $registry))
            ->refund($payment);

        self::assertSame(PaymentStatus::Refunded, $payment->getStatus());
    }

    public function testConfirmBySessionReferenceMarksPaidAndConfirmsBooking(): void
    {
        $booking = $this->pendingBooking();
        $payment = (new Payment())->setBooking($booking)->setAmount('149.70')->setReference('cs_test_1');

        $payments = $this->createStub(PaymentRepository::class);
        $payments->method('findOneByReference')->willReturn($payment);

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->expects(self::once())->method('can')->with($booking, 'confirm')->willReturn(true);
        $workflow->expects(self::once())->method('apply')->with($booking, 'confirm')->willReturn(new Marking());

        $registry = $this->createMock(Registry::class);
        $registry->expects(self::once())->method('get')->with($booking, 'booking')->willReturn($workflow);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        (new PaymentService($em, $this->createStub(PaymentProcessor::class), $payments, $registry))
            ->confirmBySessionReference('cs_test_1');

        self::assertSame(PaymentStatus::Paid, $payment->getStatus());
    }

    public function testConfirmBySessionReferenceIsIdempotentWhenAlreadyPaid(): void
    {
        // Un paiement déjà réglé (Stripe rejoue parfois l'événement) ne doit rien
        // rejouer : ni transition de réservation, ni nouvelle écriture en base.
        $payment = (new Payment())->setBooking(new Booking())->setAmount('10.00')->setStatus(PaymentStatus::Paid);

        $payments = $this->createStub(PaymentRepository::class);
        $payments->method('findOneByReference')->willReturn($payment);

        $registry = $this->createMock(Registry::class);
        $registry->expects(self::never())->method('get');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        (new PaymentService($em, $this->createStub(PaymentProcessor::class), $payments, $registry))
            ->confirmBySessionReference('cs_test_1');

        self::assertSame(PaymentStatus::Paid, $payment->getStatus());
    }

    public function testConfirmBySessionReferenceRejectsUnknownReference(): void
    {
        $payments = $this->createStub(PaymentRepository::class);
        $payments->method('findOneByReference')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $this->expectException(\InvalidArgumentException::class);

        (new PaymentService($em, $this->createStub(PaymentProcessor::class), $payments, $this->createStub(Registry::class)))
            ->confirmBySessionReference('cs_missing');
    }

    public function testRefundRejectsUnpaidPayment(): void
    {
        $payment = (new Payment())->setBooking(new Booking())->setAmount('10.00'); // statut Pending par défaut

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $this->expectException(\InvalidArgumentException::class);

        (new PaymentService($em, $this->createStub(PaymentProcessor::class), $this->createStub(PaymentRepository::class), $this->createStub(Registry::class)))
            ->refund($payment);
    }

    public function testRefundRejectsNonRefundableBooking(): void
    {
        $booking = (new Booking())->setStatus(BookingStatus::Completed); // pas remboursable
        $payment = (new Payment())->setBooking($booking)->setAmount('10.00')->setStatus(PaymentStatus::Paid);

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->expects(self::once())->method('can')->with($booking, 'refund')->willReturn(false);
        $workflow->expects(self::never())->method('apply');

        $registry = $this->createMock(Registry::class);
        $registry->expects(self::once())->method('get')->with($booking, 'booking')->willReturn($workflow);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $this->expectException(\InvalidArgumentException::class);

        (new PaymentService($em, $this->createStub(PaymentProcessor::class), $this->createStub(PaymentRepository::class), $registry))
            ->refund($payment);
    }
}
