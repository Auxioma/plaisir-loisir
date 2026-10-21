<?php

declare(strict_types=1);

namespace App\Payment\Controller;

use App\Payment\PayPal\HttpPayPalGateway;
use App\Payment\Service\PayPalConnectService;
use App\Provider\Repository\ProviderProfileRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PayPalWebhookController extends AbstractController
{
    #[Route('/webhook/paypal', name: 'paypal_webhook', methods: ['POST'])]
    public function __invoke(Request $request, HttpPayPalGateway $gateway, PayPalConnectService $connect, ProviderProfileRepository $providers, LoggerInterface $logger): Response
    {
        try {
            $event = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            if (!\is_array($event) || !$gateway->verifyWebhook($event, [
                'auth_algo' => (string) $request->headers->get('paypal-auth-algo'),
                'cert_url' => (string) $request->headers->get('paypal-cert-url'),
                'transmission_id' => (string) $request->headers->get('paypal-transmission-id'),
                'transmission_sig' => (string) $request->headers->get('paypal-transmission-sig'),
                'transmission_time' => (string) $request->headers->get('paypal-transmission-time'),
            ])) {
                return new Response('Invalid signature', 400);
            }
            if (\in_array($event['event_type'] ?? '', ['MERCHANT.ONBOARDING.COMPLETED', 'MERCHANT.PARTNER-CONSENT.REVOKED'], true)) {
                $resource = $event['resource'] ?? [];
                $merchantId = (string) ($resource['merchant_id'] ?? '');
                $trackingId = (string) ($resource['tracking_id'] ?? '');
                $provider = '' !== $merchantId ? $providers->findOneBy(['paypalMerchantId' => $merchantId]) : null;
                $provider ??= '' !== $trackingId ? $providers->findOneBy(['paypalTrackingId' => $trackingId]) : null;
                if (null !== $provider) {
                    // Relire l'autorisation actuelle protège aussi contre un ancien événement rejoué.
                    $connect->refresh($provider);
                }
            }

            return new Response('OK');
        } catch (\JsonException) {
            return new Response('Invalid payload', 400);
        } catch (\Throwable $e) {
            $logger->error('Webhook PayPal impossible : {error}', ['error' => $e->getMessage()]);

            return new Response('Retry later', 503);
        }
    }
}
