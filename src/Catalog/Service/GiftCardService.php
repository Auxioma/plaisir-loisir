<?php

declare(strict_types=1);

namespace App\Catalog\Service;

use App\Booking\Entity\Booking;
use App\Booking\Enum\BookingStatus;
use App\Booking\Service\BookingService;
use App\Catalog\Entity\GiftCard;
use App\Catalog\Entity\Service;
use App\Catalog\Enum\PricingUnit;
use App\Catalog\Enum\PromotionKind;
use App\Catalog\Repository\GiftCardRepository;
use App\Catalog\Repository\PromotionRepository;
use App\Notification\Message\SendNotificationEmail;
use App\Payment\PayPal\PayPalGateway;
use App\Payment\Service\PaymentService;
use App\Payment\Stripe\CheckoutGateway;
use App\Payment\Stripe\ConnectGateway;
use App\Provider\Service\ProviderPaymentMethods;
use App\User\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Achat d'un bon cadeau (04/10) : validation de la saisie, création du bon
 * « en attente », paiement, puis envoi du code à l'acheteur et — si l'envoi
 * par e-mail est choisi — au bénéficiaire.
 *
 * Nouveaux bons liés à une activité : paiement direct au prestataire par son
 * Stripe Connect ou son compte PayPal. Les anciens bons sans canal conservé
 * continuent d’être vérifiés selon leur ancien Checkout plateforme.
 */
final class GiftCardService
{
    public const MIN_AMOUNT = 10;
    public const MAX_AMOUNT = 1000;
    public const MAX_PEOPLE = 1000;
    public const DELIVERIES = [GiftCard::DELIVERY_EMAIL => 'Envoi instantané (email)', GiftCard::DELIVERY_PRINT => 'À imprimer', GiftCard::DELIVERY_POSTAL => 'Envoi postal'];

    /**
     * @param \Closure(): CheckoutGateway $gateway construit seulement avec une clé Stripe
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GiftCardRepository $cards,
        private readonly MessageBusInterface $bus,
        private readonly UrlGeneratorInterface $urls,
        #[AutowireServiceClosure(CheckoutGateway::class)]
        private readonly \Closure $gateway,
        private readonly ?ConnectGateway $connect = null,
        private readonly ?PayPalGateway $paypal = null,
        private readonly ?ProviderPaymentMethods $providerMethods = null,
        private readonly ?PaymentService $payments = null,
        private readonly ?PromotionRepository $promotions = null,
    ) {
    }

    /**
     * Montant d'un bon pour une activité : prix de la formule la moins chère
     * × nombre de personnes ; null si l'activité n'a pas de prix.
     */
    public function amountFor(Service $service, int $people): ?string
    {
        $lowest = null;
        $promotion = $this->promotions?->findRunningForService($service);
        foreach ($service->getPackages() as $package) {
            $cents = (int) round((float) $package->getPrice() * 100);
            if (null !== $promotion && PromotionKind::Reduction === $promotion->getKind() && ($promotion->getDiscountPercent() ?? 0) > 0) {
                $cents = (int) round((float) BookingService::discount($package->getPrice(), (int) $promotion->getDiscountPercent()) * 100);
            }
            $cents *= PricingUnit::PerPerson === $package->getPricingUnit() ? max(1, $people) : 1;
            $lowest = null === $lowest ? $cents : min($lowest, $cents);
        }

        return null === $lowest ? null : sprintf('%d.%02d', intdiv($lowest, 100), $lowest % 100);
    }

    /**
     * @param array<string, string> $data champs du formulaire
     *
     * @return array<string, string> erreurs par champ (vide si tout est valide)
     */
    public function validate(array $data, ?Service $service): array
    {
        $errors = [];
        if (null === $service) {
            $amount = $data['amount'] ?? '';
            if (!ctype_digit($amount) || (int) $amount < self::MIN_AMOUNT || (int) $amount > self::MAX_AMOUNT) {
                $errors['amount'] = sprintf('Choisissez un montant entre %d € et %d €.', self::MIN_AMOUNT, self::MAX_AMOUNT);
            }
        } else {
            $people = $data['people'] ?? '';
            if (!ctype_digit($people) || (int) $people < 1 || (int) $people > self::MAX_PEOPLE) {
                $errors['people'] = sprintf('Saisissez un nombre de personnes compris entre 1 et %d.', self::MAX_PEOPLE);
            }
        }
        if (mb_strlen(trim($data['recipient_name'] ?? '')) < 2) {
            $errors['recipient_name'] = 'Indiquez le prénom du bénéficiaire.';
        }
        $delivery = $data['delivery'] ?? '';
        if (!\array_key_exists($delivery, self::DELIVERIES)) {
            $errors['delivery'] = 'Choisissez un mode d’envoi.';
        } elseif (GiftCard::DELIVERY_EMAIL === $delivery && !filter_var($data['recipient_email'] ?? '', \FILTER_VALIDATE_EMAIL)) {
            $errors['recipient_email'] = 'Indiquez l’adresse e-mail du bénéficiaire.';
        } elseif (GiftCard::DELIVERY_POSTAL === $delivery && mb_strlen(trim($data['postal_address'] ?? '')) < 10) {
            $errors['postal_address'] = 'Indiquez l’adresse postale complète du bénéficiaire.';
        }
        if (mb_strlen($data['message'] ?? '') > 300) {
            $errors['message'] = 'Le message ne doit pas dépasser 300 caractères.';
        }
        if (mb_strlen(trim($data['buyer_name'] ?? '')) < 2) {
            $errors['buyer_name'] = 'Veuillez saisir vos nom et prénom.';
        }
        if (!filter_var($data['buyer_email'] ?? '', \FILTER_VALIDATE_EMAIL)) {
            $errors['buyer_email'] = 'Veuillez saisir une adresse e-mail valide.';
        }
        if ('' !== ($data['buyer_phone'] ?? '') && !preg_match('/^\+?[0-9 .()-]{6,20}$/', $data['buyer_phone'])) {
            $errors['buyer_phone'] = 'Numéro de téléphone invalide.';
        }
        if ('1' !== ($data['accept_terms'] ?? '')) {
            $errors['accept_terms'] = 'Vous devez accepter les conditions générales de vente.';
        }

        return $errors;
    }

    /**
     * @param array<string, string> $data saisie déjà validée
     */
    public function create(array $data, ?Service $service, ?User $buyer): GiftCard
    {
        $amount = null !== $service ? $this->amountFor($service, (int) $data['people']) : sprintf('%d.00', (int) $data['amount']);
        if (null === $amount) {
            throw new \InvalidArgumentException('Cette activité n’a pas de tarif : choisissez un bon cadeau libre.');
        }
        $delivery = $data['delivery'];

        $card = (new GiftCard())
            ->setCode($this->newCode())
            ->setService($service)
            ->setLabel(null !== $service ? sprintf('%s — %d pers.', $service->getTitle(), (int) $data['people']) : 'Bon cadeau libre')
            ->setAmount($amount)
            ->setBuyer($buyer)
            ->setBuyerName(trim($data['buyer_name']))
            ->setBuyerEmail(mb_strtolower(trim($data['buyer_email'])))
            ->setBuyerPhone(trim($data['buyer_phone'] ?? '') ?: null)
            ->setRecipientName(trim($data['recipient_name']))
            ->setRecipientEmail(GiftCard::DELIVERY_EMAIL === $delivery ? mb_strtolower(trim($data['recipient_email'])) : (trim($data['recipient_email'] ?? '') ?: null))
            ->setMessage(trim($data['message'] ?? '') ?: null)
            ->setDelivery($delivery)
            ->setPostalAddress(GiftCard::DELIVERY_POSTAL === $delivery ? trim($data['postal_address']) : null);

        $this->entityManager->persist($card);
        $this->entityManager->flush();

        return $card;
    }

    /**
     * @return string URL vers laquelle rediriger l'acheteur
     */
    public function checkout(GiftCard $card, string $channel = ProviderPaymentMethods::STRIPE): string
    {
        $service = $card->getService();
        $provider = $service?->getProvider();
        if (null === $service || null === $provider || null === $this->connect || null === $this->paypal || null === $this->providerMethods) {
            throw new \RuntimeException('Un bon cadeau doit être lié à une activité et payé sur le compte de son prestataire.');
        }
        $offered = $this->providerMethods->offered($provider);
        if (!isset($offered[$channel]) || !$offered[$channel]['online']) {
            throw new \RuntimeException('Ce moyen de paiement n’est pas configuré par le prestataire.');
        }
        $confirmation = $this->urls->generate('app_gifts_confirmation', ['id' => (string) $card->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $amount = (int) round((float) $card->getAmount() * 100);
        if (ProviderPaymentMethods::STRIPE === $channel) {
            $account = (string) $provider->getStripeAccountId();
            $session = $this->connect->createCheckout(
                $account, 'Bon cadeau — '.$card->getLabel(), $amount, $card->getCurrency(), $card->getBuyerEmail(),
                'gift-'.$card->getId(), $confirmation.(str_contains($confirmation, '?') ? '&' : '?').'session_id={CHECKOUT_SESSION_ID}',
                $this->urls->generate('app_gifts', [], UrlGeneratorInterface::ABSOLUTE_URL),
            );
            $card->setCheckoutAccount($account);
        } else {
            $merchant = (string) ($provider->getPaypalMerchantId() ?? $provider->getPaymentPaypal());
            $session = $this->paypal->createOrder(
                $merchant, 'Bon cadeau — '.$card->getLabel(), number_format((float) $card->getAmount(), 2, '.', ''),
                $card->getCurrency(), 'gift-'.$card->getId(), $confirmation,
                $this->urls->generate('app_gifts', [], UrlGeneratorInterface::ABSOLUTE_URL),
            );
            $card->setCheckoutAccount($merchant);
        }
        $card->setCheckoutReference($session->id)->setCheckoutChannel($channel);
        $this->entityManager->flush();

        return $session->url;
    }

    /** Webhook Stripe : true si la session concernait un bon cadeau. */
    public function confirmBySessionReference(string $reference): bool
    {
        $card = $this->cards->findOneByCheckoutReference($reference);
        if (null === $card) {
            return false;
        }
        $this->synchronize($card);

        return true;
    }

    /** Même vérification au retour navigateur et au webhook, sans faire confiance à l'URL. */
    public function synchronize(GiftCard $card, ?string $paypalToken = null): void
    {
        if (GiftCard::STATUS_CANCELLED === $card->getStatus()) {
            return;
        }
        if (!$card->isPaid() || null === $card->getPaidAt()) {
            if (!$this->isCheckoutPaid($card, $paypalToken)) {
                return;
            }
            $this->markPaid($card);

            return;
        }
        $this->sendEmails($card);
    }

    private function isCheckoutPaid(GiftCard $card, ?string $paypalToken): bool
    {
        $reference = $card->getCheckoutReference();
        if (null === $reference) {
            return false;
        }
        if (ProviderPaymentMethods::STRIPE === $card->getCheckoutChannel() && null !== $this->connect && null !== $card->getCheckoutAccount()) {
            $state = $this->connect->checkoutState($card->getCheckoutAccount(), $reference);

            return $state->paid && (null === $state->amountCents || $state->amountCents === (int) round((float) $card->getAmount() * 100));
        }
        if (ProviderPaymentMethods::PAYPAL === $card->getCheckoutChannel() && null !== $this->paypal && $reference === $paypalToken) {
            $state = $this->paypal->capture($reference, $card->getCheckoutAccount());

            return $state->paid && (null === $state->amountCents || $state->amountCents === (int) round((float) $card->getAmount() * 100));
        }

        // Reprise des bons créés avant le paiement direct prestataire.
        return null === $card->getCheckoutChannel() && ($this->gateway)()->isCheckoutPaid(
            $reference, (int) round((float) $card->getAmount() * 100), $card->getCurrency(), 'gift-'.$card->getId(),
        );
    }

    /** Le bon paie le total exact de l’activité du prestataire qui l’a vendu. */
    public function redeemOnBooking(string $code, Booking $booking): void
    {
        $card = $this->cards->findOneBy(['code' => strtoupper(trim($code))]);
        if (null === $card || !$card->isPaid() || GiftCard::STATUS_REDEEMED === $card->getStatus()) {
            throw new \InvalidArgumentException('Ce bon cadeau est introuvable, non réglé ou déjà utilisé.');
        }
        if (!in_array($card->getCheckoutChannel(), [ProviderPaymentMethods::STRIPE, ProviderPaymentMethods::PAYPAL], true)
            || null === $card->getCheckoutAccount()) {
            throw new \InvalidArgumentException('Ce bon a été acheté avec un ancien parcours et doit être régularisé avant utilisation.');
        }
        if (null === $card->getExpiresAt() || $card->getExpiresAt() <= new \DateTimeImmutable()) {
            throw new \InvalidArgumentException('La période de validité de ce bon cadeau est terminée.');
        }
        if (null === $booking->getService() || $card->getService()?->getId() !== $booking->getService()->getId()) {
            throw new \InvalidArgumentException('Ce bon est valable uniquement pour l’activité indiquée sur la carte.');
        }
        if ($card->getCurrency() !== $booking->getCurrency() || number_format((float) $card->getAmount(), 2, '.', '') !== number_format((float) $booking->getTotalPrice(), 2, '.', '')) {
            throw new \InvalidArgumentException('Le montant du bon doit correspondre exactement au total de cette réservation.');
        }
        if (BookingStatus::Pending !== $booking->getStatus() || null === $this->payments) {
            throw new \InvalidArgumentException('Cette réservation ne peut plus être réglée avec un bon cadeau.');
        }

        $this->entityManager->wrapInTransaction(function () use ($card, $booking): void {
            $this->entityManager->refresh($card, LockMode::PESSIMISTIC_WRITE);
            if (!$card->isPaid() || GiftCard::STATUS_REDEEMED === $card->getStatus()
                || !in_array($card->getCheckoutChannel(), [ProviderPaymentMethods::STRIPE, ProviderPaymentMethods::PAYPAL], true)
                || null === $card->getCheckoutAccount()) {
                throw new \InvalidArgumentException('Ce bon cadeau vient d’être utilisé ou n’est plus valide.');
            }
            $this->payments->recordGiftVoucherPayment($booking, $card->getCode());
            $card->redeemOn($booking);
        });
    }

    private function markPaid(GiftCard $card): void
    {
        $this->entityManager->wrapInTransaction(function () use ($card): void {
            $this->entityManager->refresh($card, LockMode::PESSIMISTIC_WRITE);
            if (GiftCard::STATUS_CANCELLED === $card->getStatus()) {
                return;
            }
            if (!$card->isPaid() || null === $card->getPaidAt()) {
                $card->markPaid(new \DateTimeImmutable());
            }
        });
        if ($card->isPaid()) {
            $this->sendEmails($card);
        }
    }

    private function sendEmails(GiftCard $card): void
    {
        $amount = number_format((float) $card->getAmount(), 2, ',', ' ').' €';
        $until = $card->getExpiresAt()?->format('d/m/Y') ?? '';
        $link = $this->urls->generate('app_gifts_confirmation', ['id' => (string) $card->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->sendOnce($card, false, new SendNotificationEmail(
            $card->getBuyerEmail(),
            sprintf('Votre bon cadeau %s est prêt', $card->getCode()),
            sprintf("Bonjour %s,\n\nMerci pour votre achat ! Votre bon cadeau « %s » d'une valeur de %s pour %s est prêt.\n\nCode : %s\nActivité valable : %s\nPrestataire : %s\nValable jusqu'au %s\n\nRetrouvez-le (et imprimez-le) ici : %s\n\nL'équipe TrouveMoi", $card->getBuyerName(), $card->getLabel(), $amount, $card->getRecipientName(), $card->getCode(), $card->getService()?->getTitle() ?? '—', $card->getService()?->getProvider()?->getDisplayName() ?? '—', $until, $link),
        ));

        if (GiftCard::DELIVERY_EMAIL === $card->getDelivery() && null !== $card->getRecipientEmail()) {
            $this->sendOnce($card, true, new SendNotificationEmail(
                $card->getRecipientEmail(),
                sprintf('%s vous offre un bon cadeau TrouveMoi', $card->getBuyerName()),
                sprintf("Bonjour %s,\n\n%s vous offre un bon cadeau « %s » d'une valeur de %s.%s\n\nVotre code : %s\nActivité valable : %s\nPrestataire : %s\nValable jusqu'au %s.\n\nRetrouvez votre bon et téléchargez-le en PDF ici : %s\n\nL'équipe TrouveMoi", $card->getRecipientName(), $card->getBuyerName(), $card->getLabel(), $amount, null !== $card->getMessage() ? "\n\nSon message : « ".$card->getMessage().' »' : '', $card->getCode(), $card->getService()?->getTitle() ?? '—', $card->getService()?->getProvider()?->getDisplayName() ?? '—', $until, $link),
            ));
        }
    }

    /** Une panne d'envoi reste réessayable, sans renvoyer l'autre e-mail déjà transmis. */
    private function sendOnce(GiftCard $card, bool $recipient, SendNotificationEmail $message): void
    {
        $this->entityManager->wrapInTransaction(function () use ($card, $recipient, $message): void {
            $this->entityManager->refresh($card, LockMode::PESSIMISTIC_WRITE);
            if (null !== ($recipient ? $card->getRecipientEmailSentAt() : $card->getBuyerEmailSentAt())) {
                return;
            }
            $this->bus->dispatch($message);
            $card->recordEmailSent($recipient);
        });
    }

    private function newCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = 'TM';
            for ($i = 0; $i < 8; ++$i) {
                $code .= (0 === $i % 4 ? '-' : '').$alphabet[random_int(0, 31)];
            }
        } while ($this->cards->codeExists($code));

        return $code;
    }
}
