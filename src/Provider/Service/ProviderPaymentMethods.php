<?php

declare(strict_types=1);

namespace App\Provider\Service;

use App\Provider\Entity\ProviderProfile;

/**
 * Moyens de paiement qu'un professionnel propose à ses clients (17/10) : le
 * client règle la prestation directement au professionnel, TrouveMoi
 * n'encaisse rien. Validation de la saisie (espace pro) et liste lisible
 * (récapitulatif et confirmation de réservation, espace pro).
 */
final class ProviderPaymentMethods
{
    /**
     * Moyens configurés, dans l'ordre d'affichage.
     *
     * @return list<array{type: string, label: string, value: string, detail: ?string}>
     */
    public function configured(ProviderProfile $provider): array
    {
        $methods = [];
        if (null !== $url = $provider->getPaymentLinkUrl()) {
            $methods[] = ['type' => 'link', 'label' => 'Paiement en ligne', 'value' => $url, 'detail' => null];
        }
        if (null !== $paypal = $provider->getPaymentPaypal()) {
            $methods[] = ['type' => 'paypal', 'label' => 'PayPal', 'value' => $paypal, 'detail' => null];
        }
        if (null !== $iban = $provider->getPaymentIban()) {
            $methods[] = ['type' => 'transfer', 'label' => 'Virement bancaire', 'value' => self::formatIban($iban), 'detail' => $provider->getPaymentIbanHolder()];
        }
        if (null !== $onSite = $provider->getPaymentOnSite()) {
            $methods[] = ['type' => 'onsite', 'label' => 'Sur place', 'value' => $onSite, 'detail' => null];
        }

        return $methods;
    }

    public function hasAny(ProviderProfile $provider): bool
    {
        return [] !== $this->configured($provider);
    }

    /**
     * Enregistre les moyens saisis dans l'espace pro ; un champ vide retire
     * le moyen correspondant.
     *
     * @param array<string, mixed> $input
     *
     * @throws \InvalidArgumentException saisie invalide (rien n'est modifié)
     */
    public function update(ProviderProfile $provider, array $input): void
    {
        $text = static fn (string $key, int $max): ?string => '' !== ($v = trim((string) ($input[$key] ?? ''))) ? mb_substr($v, 0, $max) : null;

        $link = $text('paymentLinkUrl', 255);
        if (null !== $link) {
            if (!preg_match('#^https?://#i', $link)) {
                $link = 'https://'.$link;
            }
            if (false === filter_var($link, \FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException('Lien de paiement invalide (ex. https://buy.stripe.com/…).');
            }
        }

        $paypal = $text('paymentPaypal', 180);
        if (null !== $paypal && false === filter_var($paypal, \FILTER_VALIDATE_EMAIL) && !preg_match('#^(https?://)?(www\.)?paypal\.me/[\w.-]+/?$#i', $paypal)) {
            throw new \InvalidArgumentException('PayPal : indiquez une adresse e-mail ou un lien paypal.me.');
        }

        $iban = $text('paymentIban', 60);
        if (null !== $iban) {
            $iban = strtoupper(str_replace(' ', '', $iban));
            if (!self::isValidIban($iban)) {
                throw new \InvalidArgumentException('IBAN invalide (ex. FR76 3000 6000 0112 3456 7890 189).');
            }
        }
        $holder = $text('paymentIbanHolder', 120);
        if (null !== $iban && null === $holder) {
            throw new \InvalidArgumentException('Indiquez le titulaire du compte pour le virement.');
        }

        $provider->setPaymentLinkUrl($link)
            ->setPaymentPaypal($paypal)
            ->setPaymentIban($iban)
            ->setPaymentIbanHolder(null !== $iban ? $holder : null)
            ->setPaymentOnSite($text('paymentOnSite', 255))
            ->setPaymentInstructions($text('paymentInstructions', 2000));
    }

    public static function formatIban(string $iban): string
    {
        return trim(chunk_split($iban, 4, ' '));
    }

    /** Contrôle ISO 13616 (clé modulo 97). */
    public static function isValidIban(string $iban): bool
    {
        if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $iban)) {
            return false;
        }

        $digits = '';
        foreach (str_split(substr($iban, 4).substr($iban, 0, 4)) as $char) {
            $digits .= ctype_alpha($char) ? (string) (\ord($char) - 55) : $char;
        }

        $remainder = 0;
        foreach (str_split($digits, 7) as $chunk) {
            $remainder = (int) ($remainder.$chunk) % 97;
        }

        return 1 === $remainder;
    }
}
