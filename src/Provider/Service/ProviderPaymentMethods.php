<?php

declare(strict_types=1);

namespace App\Provider\Service;

use App\Payment\PayPal\PayPalGateway;
use App\Payment\Stripe\ConnectGateway;
use App\Provider\Entity\ProviderProfile;

/**
 * Moyens de paiement qu'un professionnel accepte (20/10, maquette
 * parametre_configuration_paiement_prestataire) : paiement en ligne par
 * carte (sur SON compte Stripe, relié par Stripe Connect) ou par PayPal (sur
 * SON compte PayPal), et paiement sur place en espèces. Le client paie
 * toujours le professionnel directement ; TrouveMoi n'encaisse rien.
 */
final class ProviderPaymentMethods
{
    public const STRIPE = 'stripe';
    public const PAYPAL = 'paypal';
    public const ONSITE = 'onsite';

    public function __construct(
        private readonly ConnectGateway $stripe,
        private readonly PayPalGateway $paypal,
    ) {
    }

    /**
     * Récapitulatif côté professionnel (écran « Paiement configuré »), avec
     * l'état de chaque moyen : `active`, `pending` (coché mais compte Stripe
     * à relier, ou paiement en ligne désactivé) ou `off`.
     *
     * @return list<array{type: string, label: string, state: string, detail: ?string}>
     */
    public function summary(ProviderProfile $provider): array
    {
        $online = $provider->isOnlinePaymentEnabled();
        $state = static fn (bool $accepted, bool $ready): string => !$accepted ? 'off' : ($ready ? 'active' : 'pending');

        return [
            ['type' => 'online', 'label' => 'Paiement en ligne', 'state' => $online ? 'active' : 'off', 'detail' => null],
            ['type' => self::STRIPE, 'label' => 'Stripe CB', 'state' => $state($provider->acceptsStripe(), $online && $this->stripeReady($provider)),
                'detail' => $provider->acceptsStripe() && !$this->stripeReady($provider) ? 'Compte Stripe à relier' : null],
            ['type' => self::PAYPAL, 'label' => 'PayPal', 'state' => $state($provider->acceptsPaypal(), $online && $this->paypalReady($provider)),
                'detail' => $provider->acceptsPaypal() ? $provider->getPaymentPaypal() : null],
            ['type' => self::ONSITE, 'label' => 'Paiement sur place « Espèces »', 'state' => $provider->acceptsCash() ? 'active' : 'off', 'detail' => null],
        ];
    }

    /** Au moins un moyen est réellement proposé aux clients. */
    public function hasAny(ProviderProfile $provider): bool
    {
        return [] !== $this->offered($provider);
    }

    /**
     * Moyens proposés au client lors de sa réservation, dans l'ordre de la
     * maquette. Liste vide : rien n'est configuré, la réservation part en
     * demande et le règlement se convient avec le professionnel.
     *
     * @return array<string, array{type: string, label: string, online: bool}>
     */
    public function offered(ProviderProfile $provider): array
    {
        $methods = [];
        if ($provider->isOnlinePaymentEnabled() && $provider->acceptsStripe() && $this->stripeReady($provider)) {
            $methods[self::STRIPE] = ['type' => self::STRIPE, 'label' => 'Stripe CB', 'online' => true];
        }
        if ($provider->isOnlinePaymentEnabled() && $provider->acceptsPaypal() && $this->paypalReady($provider)) {
            $methods[self::PAYPAL] = ['type' => self::PAYPAL, 'label' => 'PayPal', 'online' => true];
        }
        if ($provider->acceptsCash()) {
            $methods[self::ONSITE] = ['type' => self::ONSITE, 'label' => 'Paiement sur place « Espèces »', 'online' => false];
        }

        return $methods;
    }

    public function stripeReady(ProviderProfile $provider): bool
    {
        return null !== $provider->getStripeAccountId() && $provider->isStripeChargesEnabled() && $this->stripe->isAvailable();
    }

    public function paypalReady(ProviderProfile $provider): bool
    {
        return null !== $provider->getPaymentPaypal()
            && false !== filter_var($provider->getPaymentPaypal(), \FILTER_VALIDATE_EMAIL)
            && $this->paypal->isAvailable();
    }

    /**
     * Enregistre la configuration saisie (étape « Configuration du
     * paiement »).
     *
     * @param array<string, mixed> $input
     *
     * @throws \InvalidArgumentException saisie invalide (rien n'est modifié)
     */
    public function update(ProviderProfile $provider, array $input): void
    {
        $flag = static fn (string $key): bool => \in_array($input[$key] ?? null, ['1', 'on', 'true', true, 1], true);
        $online = $flag('onlinePaymentEnabled');
        $stripe = $flag('acceptsStripe');
        $paypal = $flag('acceptsPaypal');
        $cash = $flag('acceptsCash');

        $paypalEmail = trim((string) ($input['paymentPaypal'] ?? ''));
        $paypalEmail = '' !== $paypalEmail ? mb_strtolower(mb_substr($paypalEmail, 0, 180)) : null;
        if (null !== $paypalEmail && false === filter_var($paypalEmail, \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('PayPal : indiquez l’adresse e-mail de votre compte PayPal.');
        }
        if ($paypal && null === $paypalEmail) {
            throw new \InvalidArgumentException('Indiquez l’adresse e-mail de votre compte PayPal pour accepter PayPal.');
        }
        if ($online && !$stripe && !$paypal) {
            throw new \InvalidArgumentException('Paiement en ligne : cochez Stripe CB et/ou PayPal.');
        }
        if (!$stripe && !$paypal && !$cash) {
            throw new \InvalidArgumentException('Acceptez au moins un moyen de paiement.');
        }

        $instructions = trim((string) ($input['paymentInstructions'] ?? ''));

        $provider->setOnlinePaymentEnabled($online)
            ->setAcceptsStripe($stripe)
            ->setAcceptsPaypal($paypal)
            ->setAcceptsCash($cash)
            ->setPaymentPaypal($paypalEmail)
            ->setPaymentInstructions('' !== $instructions ? mb_substr($instructions, 0, 2000) : null);
    }
}
