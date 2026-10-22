<?php

declare(strict_types=1);

namespace App\Tests\Payment;

use App\Payment\Stripe\StripeCheckoutGateway;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;
use Stripe\StripeClient;

final class StripeCheckoutGatewayTest extends TestCase
{
    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(CurlClient::instance());
    }

    #[DataProvider('sessions')]
    public function testOnlyTheMatchingPaidSessionIsAccepted(array $changes, bool $expected): void
    {
        $payload = array_replace([
            'id' => 'cs_test_gift', 'object' => 'checkout.session', 'mode' => 'payment',
            'payment_status' => 'paid', 'amount_total' => 5000, 'currency' => 'eur', 'client_reference_id' => 'gift-123',
        ], $changes);
        $http = $this->createMock(ClientInterface::class);
        $http->expects(self::once())->method('request')->willReturn([json_encode($payload, JSON_THROW_ON_ERROR), 200, []]);
        ApiRequestor::setHttpClient($http);
        $gateway = new StripeCheckoutGateway(new StripeClient('sk_test_fake'));
        self::assertSame($expected, $gateway->isCheckoutPaid('cs_test_gift', 5000, 'EUR', 'gift-123'));
    }

    public static function sessions(): iterable
    {
        yield 'paid matching order' => [[], true];
        yield 'unpaid' => [['payment_status' => 'unpaid'], false];
        yield 'wrong amount' => [['amount_total' => 4900], false];
        yield 'wrong currency' => [['currency' => 'usd'], false];
        yield 'another order' => [['client_reference_id' => 'gift-456'], false];
        yield 'subscription' => [['mode' => 'subscription'], false];
    }
}
