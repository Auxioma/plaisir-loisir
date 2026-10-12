<?php

declare(strict_types=1);

namespace App\Tests\Shared\Analytics;

use App\Payment\Entity\Payment;
use App\Payment\Enum\PaymentStatus;
use App\Shared\Analytics\GoogleAnalyticsRefundListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GoogleAnalyticsRefundListenerTest extends TestCase
{
    public function testARefundIsSentToTheMeasurementProtocol(): void
    {
        $sent = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$sent): MockResponse {
            $sent[] = [$method, $url, json_decode($options['body'], true)];

            return new MockResponse('', ['http_code' => 204]);
        });
        $listener = new GoogleAnalyticsRefundListener($http, new NullLogger(), 'G-TEST', 'secret');

        $payment = $this->refundedPayment();
        $listener->preUpdate($payment, $this->changeSet($payment, PaymentStatus::Paid));
        $listener->postFlush(new PostFlushEventArgs($this->createStub(EntityManagerInterface::class)));

        self::assertCount(1, $sent);
        self::assertStringContainsString('measurement_id=G-TEST', $sent[0][1]);
        self::assertSame('refund', $sent[0][2]['events'][0]['name']);
        self::assertSame(49.9, $sent[0][2]['events'][0]['params']['value']);
    }

    public function testNothingIsSentWithoutConfiguration(): void
    {
        $http = new MockHttpClient(static fn (): MockResponse => throw new \LogicException('Aucun appel attendu.'));
        $listener = new GoogleAnalyticsRefundListener($http, new NullLogger(), '', '');

        $payment = $this->refundedPayment();
        $listener->preUpdate($payment, $this->changeSet($payment, PaymentStatus::Paid));
        $listener->postFlush(new PostFlushEventArgs($this->createStub(EntityManagerInterface::class)));

        $this->addToAssertionCount(1);
    }

    private function refundedPayment(): Payment
    {
        $payment = new Payment();
        $payment->setAmount('49.90');
        $payment->setStatus(PaymentStatus::Refunded);

        return $payment;
    }

    private function changeSet(Payment $payment, PaymentStatus $old): PreUpdateEventArgs
    {
        $changes = ['status' => [$old, PaymentStatus::Refunded]];

        return new PreUpdateEventArgs($payment, $this->createStub(EntityManagerInterface::class), $changes);
    }
}
