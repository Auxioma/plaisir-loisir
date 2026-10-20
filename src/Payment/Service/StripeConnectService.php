<?php

declare(strict_types=1);

namespace App\Payment\Service;

use App\Payment\Stripe\ConnectGateway;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Repository\ProviderProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\RateLimitException;

/**
 * Liaison du compte Stripe d'un professionnel (Stripe Connect, compte
 * Standard) : création du compte, inscription sur les pages de Stripe
 * (identité, IBAN), puis suivi de l'autorisation de paiement
 * (`charges_enabled`), au retour du professionnel et par le webhook
 * `account.updated`.
 */
final class StripeConnectService
{
    public function __construct(
        private readonly ConnectGateway $gateway,
        private readonly ProviderProfileRepository $providers,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->gateway->isAvailable();
    }

    /**
     * Adresse de l'inscription Stripe (le compte est créé au premier appel).
     *
     * @throws \InvalidArgumentException Stripe indisponible
     */
    public function onboardingUrl(ProviderProfile $provider, string $refreshUrl, string $returnUrl): string
    {
        if (!$this->gateway->isAvailable()) {
            throw new \InvalidArgumentException('Le paiement par carte n’est pas encore disponible sur TrouveMoi.');
        }

        try {
            // Un compte simulé (créé sans clé Stripe, ou par les fixtures)
            // n'existe pas chez Stripe : on en crée un vrai à sa place.
            $accountId = $provider->getStripeAccountId();
            if (null === $accountId || str_starts_with($accountId, 'mock_')) {
                $provider->setStripeChargesEnabled(false)
                    ->setStripeAccountId($this->gateway->createAccount((string) $provider->getUser()?->getEmail(), (string) $provider->getId()));
                $this->entityManager->flush();
            }

            return $this->gateway->onboardingUrl((string) $provider->getStripeAccountId(), $refreshUrl, $returnUrl);
        } catch (\Throwable $e) {
            $this->logger->error('Stripe Connect : inscription impossible pour {id} : {error}', ['id' => (string) $provider->getId(), 'error' => $e->getMessage()]);

            // Refus de Stripe (Connect non activé, clé invalide…) : on dit
            // pourquoi au lieu d'un « injoignable » trompeur (20/10).
            if ($e instanceof ApiErrorException && !$e instanceof ApiConnectionException && !$e instanceof RateLimitException) {
                throw new \InvalidArgumentException(sprintf('Stripe a refusé la liaison : %s', $e->getMessage()), 0, $e);
            }

            throw new \InvalidArgumentException('Stripe est momentanément injoignable. Réessayez dans un instant.', 0, $e);
        }
    }

    /** Relit l'état du compte chez Stripe ; renvoie true si les paiements sont autorisés. */
    public function refresh(ProviderProfile $provider): bool
    {
        if (null === $provider->getStripeAccountId()) {
            return false;
        }

        try {
            $provider->setStripeChargesEnabled($this->gateway->chargesEnabled($provider->getStripeAccountId()));
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->logger->error('Stripe Connect : état du compte {account} illisible : {error}', ['account' => $provider->getStripeAccountId(), 'error' => $e->getMessage()]);
        }

        return $provider->isStripeChargesEnabled();
    }

    /** Délie le compte (il reste à son titulaire chez Stripe). */
    public function disconnect(ProviderProfile $provider): void
    {
        $provider->setStripeAccountId(null)->setStripeChargesEnabled(false)->setAcceptsStripe(false);
        $this->entityManager->flush();
    }

    /** Webhook `account.updated`. */
    public function sync(string $accountId, bool $chargesEnabled): void
    {
        $provider = '' !== $accountId ? $this->providers->findOneBy(['stripeAccountId' => $accountId]) : null;
        if (null !== $provider) {
            $provider->setStripeChargesEnabled($chargesEnabled);
            $this->entityManager->flush();
        }
    }
}
