<?php

declare(strict_types=1);

namespace App\Tests\Payment\Service;

use App\Booking\Entity\Booking;
use App\Booking\Enum\BookingStatus;
use App\Notification\Repository\NotificationRepository;
use App\Notification\Service\NotificationService;
use App\Payment\Entity\Payment;
use App\Payment\Enum\PaymentStatus;
use App\Payment\Repository\PaymentRepository;
use App\Payment\Service\PaymentService;
use App\Payment\Stripe\CheckoutState;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Règlement direct au professionnel (17/10, paiement en ligne le 20/10) :
 * demande « sur place » sans rien débiter, règlement déclaré par le
 * professionnel, paiement en ligne confirmé par Stripe / PayPal (réservation
 * confirmée), numéro de facture.
 */
final class PaymentServiceTest extends TestCase
{
    private function pendingBooking(): Booking
    {
        return (new Booking())->setTotalPrice('149.70')->setCurrency('EUR');
    }

    private function service(PaymentRepository $payments, ?EntityManagerInterface $em = null, ?WorkflowInterface $workflow = null): PaymentService
    {
        return new PaymentService(
            $em ?? $this->createStub(EntityManagerInterface::class),
            $payments,
            new NotificationService($this->createStub(EntityManagerInterface::class), $this->createStub(NotificationRepository::class)),
            new LockFactory(new InMemoryStore()),
            new NullLogger(),
            $workflow ?? $this->createStub(WorkflowInterface::class),
        );
    }

    public function testRequestCreatesAnUnpaidOnSiteSettlementWithoutCharging(): void
    {
        $booking = $this->pendingBooking();
        $payments = $this->createStub(PaymentRepository::class);
        $payments->method('findOneByBooking')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(Payment::class));
        $em->expects(self::once())->method('flush');

        $payment = $this->service($payments, $em)->request($booking);

        self::assertSame(PaymentStatus::Pending, $payment->getStatus());
        self::assertSame('149.70', $payment->getAmount());
        self::assertSame(Payment::CHANNEL_ONSITE, $payment->getChannel());
        self::assertNull($payment->getMethod());
        self::assertSame(BookingStatus::Pending, $booking->getStatus(), 'C’est le professionnel qui confirme la réservation.');
    }

    public function testRequestCannotBeSentTwice(): void
    {
        $payments = $this->createStub(PaymentRepository::class);
        $payments->method('findOneByBooking')->willReturn((new Payment())->setChannel(Payment::CHANNEL_ONSITE));

        $this->expectException(\InvalidArgumentException::class);
        $this->service($payments)->request($this->pendingBooking());
    }

    public function testAnUnfinishedOnlinePaymentSwitchesToOnSite(): void
    {
        $payment = (new Payment())->setAmount('149.70')->setChannel(Payment::CHANNEL_STRIPE)->setExternalId('cs_test_1')->setExternalAccount('acct_1');
        $payments = $this->createStub(PaymentRepository::class);
        $payments->method('findOneByBooking')->willReturn($payment);

        $this->service($payments)->request($this->pendingBooking());

        self::assertSame(Payment::CHANNEL_ONSITE, $payment->getChannel());
        self::assertNull($payment->getExternalId());
    }

    public function testRequestRejectsANonPendingBooking(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service($this->createStub(PaymentRepository::class))
            ->request($this->pendingBooking()->setStatus(BookingStatus::Cancelled));
    }

    public function testMarkPaidRecordsTheMethodDateAndInvoiceNumber(): void
    {
        $booking = $this->pendingBooking()->setStatus(BookingStatus::Confirmed);
        $payment = (new Payment())->setBooking($booking)->setAmount('149.70');
        $payments = $this->createStub(PaymentRepository::class);
        $payments->method('findOneByBooking')->willReturn($payment);
        $payments->method('lastInvoiceNumber')->willReturn(sprintf('FACT-%s-00041', date('Y')));

        $this->service($payments)->markPaid($booking, 'cash');

        self::assertSame(PaymentStatus::Paid, $payment->getStatus());
        self::assertSame('cash', $payment->getMethod());
        self::assertNotNull($payment->getPaidAt());
        self::assertSame(sprintf('FACT-%s-00042', date('Y')), $payment->getInvoiceNumber());
    }

    public function testMarkPaidCreatesTheSettlementWhenMissing(): void
    {
        $payments = $this->createStub(PaymentRepository::class);
        $payments->method('findOneByBooking')->willReturn(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(Payment::class));

        $payment = $this->service($payments, $em)->markPaid($this->pendingBooking()->setStatus(BookingStatus::Confirmed), 'onsite');

        self::assertSame(PaymentStatus::Paid, $payment->getStatus());
        self::assertSame(sprintf('FACT-%s-00001', date('Y')), $payment->getInvoiceNumber(), 'Première facture de l’année.');
    }

    public function testMarkPaidRejectsAnUnknownMethodOrACancelledBooking(): void
    {
        $service = $this->service($this->createStub(PaymentRepository::class));

        try {
            $service->markPaid($this->pendingBooking()->setStatus(BookingStatus::Confirmed), 'bitcoin');
            self::fail('Un moyen inconnu doit être refusé.');
        } catch (\InvalidArgumentException) {
        }

        $this->expectException(\InvalidArgumentException::class);
        $service->markPaid($this->pendingBooking()->setStatus(BookingStatus::Cancelled), 'cash');
    }

    public function testAConfirmedOnlinePaymentConfirmsTheBooking(): void
    {
        $booking = $this->pendingBooking();
        $payment = (new Payment())->setBooking($booking)->setAmount('149.70')->setChannel(Payment::CHANNEL_STRIPE);
        $payments = $this->createStub(PaymentRepository::class);

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('can')->willReturn(true);
        $workflow->expects(self::once())->method('apply')->with($booking, 'confirm', ['paid_online' => true]);

        $state = new CheckoutState(true, 'pi_123', 'visa', '4242', 14970);
        self::assertTrue($this->service($payments, null, $workflow)->recordOnlinePayment($payment, $state));

        self::assertSame(PaymentStatus::Paid, $payment->getStatus());
        self::assertSame('card', $payment->getMethod());
        self::assertSame('pi_123', $payment->getTransactionId());
        self::assertSame('4242', $payment->getCardLast4());
        self::assertNotNull($payment->getInvoiceNumber());

        // Retour du client puis webhook : la seconde confirmation ne refait rien.
        self::assertTrue($this->service($payments, null, $workflow)->recordOnlinePayment($payment, $state));
    }

    public function testAnOnlinePaymentOfTheWrongAmountIsNotAccepted(): void
    {
        $payment = (new Payment())->setBooking($this->pendingBooking())->setAmount('149.70')->setChannel(Payment::CHANNEL_PAYPAL);
        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->expects(self::never())->method('apply');

        self::assertFalse($this->service($this->createStub(PaymentRepository::class), null, $workflow)
            ->recordOnlinePayment($payment, new CheckoutState(true, 'CAPTURE1', amountCents: 100)));
        self::assertSame(PaymentStatus::Pending, $payment->getStatus());
    }

    public function testAnUnpaidCheckoutChangesNothing(): void
    {
        $payment = (new Payment())->setBooking($this->pendingBooking())->setAmount('149.70')->setChannel(Payment::CHANNEL_STRIPE);

        self::assertFalse($this->service($this->createStub(PaymentRepository::class))->recordOnlinePayment($payment, new CheckoutState(false)));
        self::assertSame(PaymentStatus::Pending, $payment->getStatus());
    }
}
