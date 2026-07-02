<?php

declare(strict_types=1);

namespace App\Tests\Payment\Stripe;

use App\Booking\Entity\Booking;
use App\Booking\Enum\BookingStatus;
use App\Catalog\Entity\Service;
use App\Payment\Entity\Payment;
use App\Payment\Enum\PaymentStatus;
use App\Payment\Repository\PaymentRepository;
use App\Payment\Stripe\CheckoutGateway;
use App\Payment\Stripe\CheckoutSessionResult;
use App\Payment\Stripe\StripeCheckoutService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class StripeCheckoutServiceTest extends TestCase
{
    public function testStartCheckoutCreatesPendingPaymentAndReturnsPaymentUrl(): void
    {
        $service = (new Service())->setTitle('Sortie kayak');
        $booking = (new Booking())->setService($service)->setTotalPrice('149.70')->setCurrency('EUR');

        $payments = $this->createStub(PaymentRepository::class);
        $payments->method('findOneByBooking')->willReturn(null);

        // On vérifie au passage la conversion en centimes (149.70 -> 14970) et la devise.
        $gateway = $this->createMock(CheckoutGateway::class);
        $gateway->expects(self::once())->method('createCheckoutSession')
            ->with('Sortie kayak', 14970, 'EUR', self::anything(), self::anything(), self::anything())
            ->willReturn(new CheckoutSessionResult('cs_test_123', 'https://stripe.test/pay'));

        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('http://localhost/retour');

        $captured = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')
            ->willReturnCallback(static function (Payment $payment) use (&$captured): void {
                $captured = $payment;
            });
        // Deux flush : à la création du paiement, puis après avoir mémorisé la session.
        $em->expects(self::exactly(2))->method('flush');

        $url = (new StripeCheckoutService($em, $payments, $gateway, $urls))->startCheckout($booking);

        self::assertSame('https://stripe.test/pay', $url);
        self::assertInstanceOf(Payment::class, $captured);
        self::assertSame(PaymentStatus::Pending, $captured->getStatus());
        self::assertSame('cs_test_123', $captured->getReference());
        self::assertSame('149.70', $captured->getAmount());
    }

    public function testStartCheckoutRejectsNonPendingBooking(): void
    {
        $booking = (new Booking())->setTotalPrice('10.00')->setStatus(BookingStatus::Confirmed);

        $gateway = $this->createMock(CheckoutGateway::class);
        $gateway->expects(self::never())->method('createCheckoutSession');

        $this->expectException(\InvalidArgumentException::class);

        (new StripeCheckoutService(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(PaymentRepository::class),
            $gateway,
            $this->createStub(UrlGeneratorInterface::class),
        ))->startCheckout($booking);
    }

    public function testStartCheckoutRejectsBookingThatAlreadyHasPayment(): void
    {
        $booking = (new Booking())->setTotalPrice('10.00');

        $payments = $this->createStub(PaymentRepository::class);
        $payments->method('findOneByBooking')->willReturn(new Payment());

        $gateway = $this->createMock(CheckoutGateway::class);
        $gateway->expects(self::never())->method('createCheckoutSession');

        $this->expectException(\InvalidArgumentException::class);

        (new StripeCheckoutService(
            $this->createStub(EntityManagerInterface::class),
            $payments,
            $gateway,
            $this->createStub(UrlGeneratorInterface::class),
        ))->startCheckout($booking);
    }
}
