<?php

declare(strict_types=1);

namespace App\Tests\Payment\PayPal;

use App\Payment\PayPal\HttpPayPalGateway;
use App\Payment\Service\PayPalConnectService;
use App\Provider\Entity\ProviderProfile;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PayPalConnectTest extends TestCase
{
    public function testVerifiedMerchantIsLinkedThenCanBeUnlinkedAndLinkedAgain(): void
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$method, $url, $options];
            $data = match (true) {
                str_ends_with($url, '/token') => ['access_token' => 'access'],
                str_ends_with($url, '/partner-referrals') => ['links' => [['rel' => 'action_url', 'href' => 'https://www.sandbox.paypal.com/onboard']]],
                str_contains($url, '?tracking_id=') => ['merchant_id' => 'SELLER'],
                default => ['merchant_id' => 'SELLER', 'payments_receivable' => true, 'primary_email_confirmed' => true,
                    'primary_email' => 'seller@example.com', 'oauth_integrations' => [['oauth_third_party' => [['partner_client_id' => 'client', 'scopes' => ['https://uri.paypal.com/services/payments/payment/authcapture']]]]]],
            };

            return new MockResponse(json_encode($data, \JSON_THROW_ON_ERROR));
        });
        $gateway = new HttpPayPalGateway($client, 'client', 'secret', 'sandbox', 'PARTNER');
        $em = $this->createStub(EntityManagerInterface::class);
        $service = new PayPalConnectService($gateway, $em, new NullLogger());
        $provider = (new ProviderProfile())->setOnlinePaymentEnabled(true);
        self::assertSame('https://www.sandbox.paypal.com/onboard', $service->onboardingUrl($provider, 'https://example.com/return'));
        $firstTracking = $provider->getPaypalTrackingId();
        self::assertTrue($service->refresh($provider));
        self::assertSame('SELLER', $provider->getPaypalMerchantId());
        self::assertSame('seller@example.com', $provider->getPaymentPaypal());
        self::assertTrue($provider->acceptsPaypal());
        $service->disconnect($provider);
        self::assertNull($provider->getPaypalMerchantId());
        self::assertNull($provider->getPaypalTrackingId());
        self::assertNull($provider->getPaymentPaypal());
        self::assertFalse($provider->isPaypalPaymentsEnabled());
        self::assertFalse($provider->isOnlinePaymentEnabled());
        self::assertFalse($service->refresh($provider), 'Un ancien retour après déliaison ne doit pas réactiver le compte.');
        $service->onboardingUrl($provider, 'https://example.com/return');
        self::assertNotSame($firstTracking, $provider->getPaypalTrackingId());
        self::assertTrue($service->refresh($provider));
        self::assertStringContainsString('tracking_id=', $requests[3][1]);
    }

    public function testOrdersUseTheVerifiedSellerForCreationAndCapture(): void
    {
        $assertions = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$assertions): MockResponse {
            if (str_ends_with($url, '/token')) {
                return new MockResponse('{"access_token":"access"}');
            }
            $assertions[] = $options['normalized_headers']['paypal-auth-assertion'][0] ?? '';
            if (str_ends_with($url, '/orders')) {
                $body = json_decode($options['body'], true);
                self::assertSame('SELLER', $body['purchase_units'][0]['payee']['merchant_id']);

                return new MockResponse('{"id":"ORDER","links":[{"rel":"approve","href":"https://www.sandbox.paypal.com/approve"}]}');
            }
            if ('GET' === $method) {
                return new MockResponse('{"status":"APPROVED"}');
            }

            return new MockResponse('{"status":"COMPLETED","purchase_units":[{"payments":{"captures":[{"id":"CAPTURE","status":"COMPLETED","amount":{"value":"12.00"}}]}}]}');
        });
        $gateway = new HttpPayPalGateway($client, 'client', 'secret', 'sandbox', 'PARTNER');
        self::assertSame('ORDER', $gateway->createOrder('SELLER', 'Sortie', '12.00', 'EUR', 'ref', 'https://example.com/return', 'https://example.com/cancel')->id);
        self::assertTrue($gateway->capture('ORDER', 'SELLER')->paid);
        self::assertCount(3, $assertions);
        foreach ($assertions as $assertion) {
            self::assertStringContainsString('paypal-auth-assertion:', strtolower($assertion));
            $parts = explode('.', substr($assertion, strpos($assertion, ':') + 2));
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            self::assertSame('SELLER', $payload['payer_id']);
            self::assertSame('client', $payload['iss']);
        }
    }

    public function testWebhookVerificationUsesPayPalAndRejectsMissingSignature(): void
    {
        $client = new MockHttpClient([
            new MockResponse('{"access_token":"access"}'),
            new MockResponse('{"verification_status":"SUCCESS"}'),
            new MockResponse('{"access_token":"access"}'),
            new MockResponse('{"verification_status":"FAILURE"}'),
        ]);
        $gateway = new HttpPayPalGateway($client, 'client', 'secret', 'sandbox', 'PARTNER', 'WEBHOOK');
        self::assertFalse($gateway->verifyWebhook(['id' => 'event'], []));
        $headers = ['auth_algo' => 'SHA256withRSA', 'cert_url' => 'https://api.paypal.com/cert',
            'transmission_id' => 'transmission', 'transmission_sig' => 'signature', 'transmission_time' => '2026-10-21T12:00:00Z'];
        self::assertTrue($gateway->verifyWebhook(['id' => 'event'], $headers));
        self::assertFalse($gateway->verifyWebhook(['id' => 'event'], $headers));
    }

    public function testUnconfirmedAccountDoesNotEnablePayments(): void
    {
        $client = new MockHttpClient([
            new MockResponse('{"access_token":"access"}'), new MockResponse('{"merchant_id":"SELLER"}'),
            new MockResponse('{"access_token":"access"}'), new MockResponse('{"merchant_id":"SELLER","payments_receivable":true,"primary_email_confirmed":false}'),
        ]);
        $service = new PayPalConnectService(new HttpPayPalGateway($client, 'client', 'secret', 'sandbox', 'PARTNER'), $this->createStub(EntityManagerInterface::class), new NullLogger());
        $provider = (new ProviderProfile())->setPaypalTrackingId('tracking');
        self::assertFalse($service->refresh($provider));
        self::assertFalse($provider->isPaypalPaymentsEnabled());
        self::assertNull($provider->getPaypalMerchantId());
    }
}
