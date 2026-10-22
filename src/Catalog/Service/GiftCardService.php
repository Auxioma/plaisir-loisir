<?php

declare(strict_types=1);

namespace App\Catalog\Service;

use App\Catalog\Entity\GiftCard;
use App\Catalog\Entity\Service;
use App\Catalog\Repository\GiftCardRepository;
use App\Notification\Message\SendNotificationEmail;
use App\Payment\Stripe\CheckoutGateway;
use App\User\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Achat d'un bon cadeau (04/10) : validation de la saisie, création du bon
 * « en attente », paiement, puis envoi du code à l'acheteur et — si l'envoi
 * par e-mail est choisi — au bénéficiaire.
 *
 * Paiement encaissé par TrouveMoi (le bon est vendu par la plateforme). Avec une clé
 * Stripe, page de paiement hébergée et confirmation par le webhook ou au retour ; sans
 * clé (développement, démo), le paiement est simulé et réglé aussitôt.
 * Sans clé en production, l’achat est refusé.
 */
final class GiftCardService
{
    public const MIN_AMOUNT = 10;
    public const MAX_AMOUNT = 1000;
    public const MAX_PEOPLE = 10;
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
        #[Autowire('%env(STRIPE_SECRET_KEY)%')]
        private readonly string $stripeSecretKey,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment = 'dev',
    ) {
    }

    /**
     * Montant d'un bon pour une activité : prix de la formule la moins chère
     * × nombre de personnes ; null si l'activité n'a pas de prix.
     */
    public function amountFor(Service $service, int $people): ?string
    {
        $lowest = null;
        foreach ($service->getPackages() as $package) {
            $cents = (int) round((float) $package->getPrice() * 100);
            $lowest = null === $lowest ? $cents : min($lowest, $cents);
        }

        return null === $lowest ? null : sprintf('%d.%02d', intdiv($lowest * $people, 100), ($lowest * $people) % 100);
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
                $errors['people'] = sprintf('Choisissez entre 1 et %d personnes.', self::MAX_PEOPLE);
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
    public function checkout(GiftCard $card): string
    {
        $confirmation = $this->urls->generate('app_gifts_confirmation', ['id' => (string) $card->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        if (!str_starts_with($this->stripeSecretKey, 'sk_')) {
            if ('prod' === $this->environment) {
                throw new \RuntimeException('Le paiement des bons cadeaux n’est pas configuré.');
            }
            $this->markPaid($card);

            return $confirmation;
        }

        $session = ($this->gateway)()->createCheckoutSession(
            label: 'Bon cadeau TrouveMoi — '.$card->getLabel(),
            amountCents: (int) round((float) $card->getAmount() * 100),
            currency: $card->getCurrency(),
            reference: 'gift-'.$card->getId(),
            successUrl: $confirmation,
            cancelUrl: $this->urls->generate('app_gifts', [], UrlGeneratorInterface::ABSOLUTE_URL),
        );
        $card->setCheckoutReference($session->id);
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
    public function synchronize(GiftCard $card): void
    {
        if (GiftCard::STATUS_CANCELLED === $card->getStatus()) {
            return;
        }
        if (!$card->isPaid() || null === $card->getPaidAt()) {
            if (null === $card->getCheckoutReference() || !($this->gateway)()->isCheckoutPaid(
                $card->getCheckoutReference(),
                (int) round((float) $card->getAmount() * 100),
                $card->getCurrency(),
                'gift-'.$card->getId(),
            )) {
                return;
            }
            $this->markPaid($card);

            return;
        }
        $this->sendEmails($card);
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
            sprintf("Bonjour %s,\n\nMerci pour votre achat ! Votre bon cadeau « %s » d'une valeur de %s pour %s est prêt.\n\nCode : %s\nValable jusqu'au %s\n\nRetrouvez-le (et imprimez-le) ici : %s\n\nL'équipe TrouveMoi", $card->getBuyerName(), $card->getLabel(), $amount, $card->getRecipientName(), $card->getCode(), $until, $link),
        ));

        if (GiftCard::DELIVERY_EMAIL === $card->getDelivery() && null !== $card->getRecipientEmail()) {
            $this->sendOnce($card, true, new SendNotificationEmail(
                $card->getRecipientEmail(),
                sprintf('%s vous offre un bon cadeau TrouveMoi', $card->getBuyerName()),
                sprintf("Bonjour %s,\n\n%s vous offre un bon cadeau « %s » d'une valeur de %s.%s\n\nVotre code : %s\nValable jusqu'au %s.\n\nRetrouvez votre bon et téléchargez-le en PDF ici : %s\n\nL'équipe TrouveMoi", $card->getRecipientName(), $card->getBuyerName(), $card->getLabel(), $amount, null !== $card->getMessage() ? "\n\nSon message : « ".$card->getMessage().' »' : '', $card->getCode(), $until, $link),
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
