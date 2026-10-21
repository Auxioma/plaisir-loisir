<?php

declare(strict_types=1);

namespace App\Payment\Service;

use App\Payment\PayPal\HttpPayPalGateway;
use App\Provider\Entity\ProviderProfile;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class PayPalConnectService
{
    public function __construct(
        private readonly HttpPayPalGateway $gateway,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function hasCredentials(): bool
    {
        return $this->gateway->isAvailable();
    }

    public function isAvailable(): bool
    {
        return $this->gateway->onboardingAvailable();
    }

    public function onboardingUrl(ProviderProfile $provider, string $returnUrl): string
    {
        if (!$this->isAvailable()) {
            throw new \InvalidArgumentException('La liaison PayPal n’est pas encore disponible.');
        }
        // Une nouvelle tentative invalide tout retour d'une ancienne liaison.
        $provider->setPaypalTrackingId(bin2hex(random_bytes(24)));
        $this->entityManager->flush();
        try {
            return $this->gateway->onboardingUrl((string) $provider->getPaypalTrackingId(), $returnUrl);
        } catch (\Throwable $e) {
            $this->logger->error('Liaison PayPal impossible : {error}', ['error' => $e->getMessage()]);
            throw new \InvalidArgumentException('La liaison PayPal n’a pas pu démarrer. Réessayez dans un instant.', 0, $e);
        }
    }

    public function refresh(ProviderProfile $provider): bool
    {
        if (null === $provider->getPaypalTrackingId() || !$this->isAvailable()) {
            return false;
        }
        try {
            $merchant = $this->gateway->merchantForTracking($provider->getPaypalTrackingId());
            $enabled = true === ($merchant['payments_receivable'] ?? false)
                && true === ($merchant['primary_email_confirmed'] ?? false)
                && $this->gateway->paymentsAuthorized($merchant)
                && !empty($merchant['merchant_id'])
                && false !== filter_var($merchant['primary_email'] ?? '', \FILTER_VALIDATE_EMAIL);
            $provider->setPaypalPaymentsEnabled($enabled);
            if ($enabled) {
                $provider->setPaypalMerchantId((string) $merchant['merchant_id'])
                    ->setPaymentPaypal(mb_strtolower((string) $merchant['primary_email']))
                    ->setAcceptsPaypal(true);
            }
            $this->entityManager->flush();

            return $enabled;
        } catch (\Throwable $e) {
            $this->logger->error('État PayPal illisible : {error}', ['error' => $e->getMessage()]);
            // Ne pas accepter de nouveaux paiements si le statut ne peut pas être vérifié.
            $provider->setPaypalPaymentsEnabled(false);
            $this->entityManager->flush();

            return false;
        }
    }

    public function disconnect(ProviderProfile $provider): void
    {
        $provider->setPaypalMerchantId(null)->setPaypalTrackingId(null)
            ->setPaypalPaymentsEnabled(false)->setPaymentPaypal(null)->setAcceptsPaypal(false);
        if (!$provider->acceptsStripe()) {
            $provider->setOnlinePaymentEnabled(false);
        }
        $this->entityManager->flush();
    }
}
