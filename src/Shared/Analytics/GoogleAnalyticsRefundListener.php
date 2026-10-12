<?php

declare(strict_types=1);

namespace App\Shared\Analytics;

use App\Payment\Entity\Payment;
use App\Payment\Enum\PaymentStatus;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Déclare à Google Analytics les remboursements (12/10, demande du client :
 * « le tracking lié aux ventes et éventuellement aux remboursements »).
 *
 * Un remboursement se décide côté serveur (back-office, Stripe, annulation) :
 * aucun navigateur n'est là pour l'envoyer. Il part donc par le Measurement
 * Protocol de GA4, avec le même transaction_id (référence de la réservation)
 * que l'achat, ce qui permet à GA de le rapprocher. Désactivé tant que
 * GA_MEASUREMENT_ID et GA_API_SECRET ne sont pas renseignés ; une panne de
 * Google n'empêche jamais le remboursement (erreur journalisée seulement).
 */
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: Payment::class)]
#[AsDoctrineListener(event: Events::postFlush)]
final class GoogleAnalyticsRefundListener
{
    /** @var list<Payment> */
    private array $refunded = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(GA_MEASUREMENT_ID)%')] private readonly string $measurementId,
        #[Autowire('%env(GA_API_SECRET)%')] private readonly string $apiSecret,
    ) {
    }

    public function preUpdate(Payment $payment, PreUpdateEventArgs $event): void
    {
        if ($event->hasChangedField('status') && PaymentStatus::Refunded === $payment->getStatus() && PaymentStatus::Refunded !== $event->getOldValue('status')) {
            $this->refunded[] = $payment;
        }
    }

    public function postFlush(PostFlushEventArgs $event): void
    {
        if ([] === $this->refunded) {
            return;
        }
        $payments = $this->refunded;
        $this->refunded = [];
        if ('' === $this->measurementId || '' === $this->apiSecret) {
            return;
        }

        foreach ($payments as $payment) {
            $reference = $payment->getBooking()?->getReference() ?? (string) $payment->getId();
            try {
                $this->httpClient->request('POST', 'https://www.google-analytics.com/mp/collect', [
                    'query' => ['measurement_id' => $this->measurementId, 'api_secret' => $this->apiSecret],
                    'json' => [
                        // Identifiant technique stable, sans donnée personnelle.
                        'client_id' => \sprintf('%u.%d', crc32($reference), 1),
                        'events' => [[
                            'name' => 'refund',
                            'params' => [
                                'transaction_id' => $reference,
                                'currency' => $payment->getCurrency() ?: 'EUR',
                                'value' => (float) $payment->getAmount(),
                            ],
                        ]],
                    ],
                    'timeout' => 5,
                ])->getStatusCode();
            } catch (\Throwable $e) {
                $this->logger->warning('Remboursement non déclaré à Google Analytics.', ['reference' => $reference, 'exception' => $e]);
            }
        }
    }
}
