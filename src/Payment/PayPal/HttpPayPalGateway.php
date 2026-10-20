<?php

declare(strict_types=1);

namespace App\Payment\PayPal;

use App\Payment\Stripe\CheckoutSessionResult;
use App\Payment\Stripe\CheckoutState;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * API REST PayPal (Orders v2) : https://developer.paypal.com/api/rest/
 * `PAYPAL_MODE=sandbox` pour les tests, `live` en production.
 */
final class HttpPayPalGateway implements PayPalGateway
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(PAYPAL_CLIENT_ID)%')]
        private readonly string $clientId,
        #[Autowire('%env(PAYPAL_CLIENT_SECRET)%')]
        private readonly string $clientSecret,
        #[Autowire('%env(PAYPAL_MODE)%')]
        private readonly string $mode,
    ) {
    }

    public function isAvailable(): bool
    {
        return '' !== $this->clientId && '' !== $this->clientSecret;
    }

    public function createOrder(string $payeeEmail, string $label, string $amount, string $currency, string $reference, string $returnUrl, string $cancelUrl): CheckoutSessionResult
    {
        $order = $this->request('POST', '/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $reference,
                'custom_id' => $reference,
                'description' => mb_substr($label, 0, 127),
                'amount' => ['currency_code' => strtoupper($currency), 'value' => $amount],
                'payee' => ['email_address' => $payeeEmail],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'brand_name' => 'TrouveMoi',
                'locale' => 'fr-FR',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ]]],
        ], ['PayPal-Request-Id' => 'order-'.$reference.'-'.bin2hex(random_bytes(4))]);

        foreach ($order['links'] ?? [] as $link) {
            if (\in_array($link['rel'] ?? '', ['payer-action', 'approve'], true)) {
                return new CheckoutSessionResult((string) $order['id'], (string) $link['href']);
            }
        }

        throw new \RuntimeException('PayPal n’a pas renvoyé de lien de paiement.');
    }

    public function capture(string $orderId): CheckoutState
    {
        $order = $this->request('GET', '/v2/checkout/orders/'.rawurlencode($orderId));
        if ('APPROVED' === ($order['status'] ?? null)) {
            $order = $this->request('POST', '/v2/checkout/orders/'.rawurlencode($orderId).'/capture', null, ['PayPal-Request-Id' => 'capture-'.$orderId]);
        }

        $capture = $order['purchase_units'][0]['payments']['captures'][0] ?? null;
        if ('COMPLETED' !== ($order['status'] ?? null) || 'COMPLETED' !== ($capture['status'] ?? null)) {
            return new CheckoutState(false);
        }

        return new CheckoutState(
            paid: true,
            transactionId: (string) $capture['id'],
            amountCents: (int) round((float) $capture['amount']['value'] * 100),
        );
    }

    /**
     * @param array<string, mixed>|null $json    null : corps vide (capture)
     * @param array<string, string>     $headers
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $json = null, array $headers = []): array
    {
        $options = ['auth_bearer' => $this->accessToken(), 'headers' => $headers + ['Content-Type' => 'application/json']];
        if ('POST' === $method) {
            $options['body'] = null !== $json ? json_encode($json, \JSON_THROW_ON_ERROR) : '{}';
        }

        $response = $this->httpClient->request($method, $this->baseUrl().$path, $options);
        $data = $response->toArray(false);
        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException(sprintf('PayPal a refusé la demande (%d %s).', $response->getStatusCode(), (string) ($data['details'][0]['issue'] ?? $data['name'] ?? '')));
        }

        return $data;
    }

    private function accessToken(): string
    {
        $response = $this->httpClient->request('POST', $this->baseUrl().'/v1/oauth2/token', [
            'auth_basic' => [$this->clientId, $this->clientSecret],
            'body' => ['grant_type' => 'client_credentials'],
        ]);

        return (string) $response->toArray()['access_token'];
    }

    private function baseUrl(): string
    {
        return 'live' === $this->mode ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }
}
