<?php

declare(strict_types=1);

namespace App\Payment\Repository;

use App\Booking\Entity\Booking;
use App\Payment\Entity\Payment;
use App\Provider\Entity\ProviderProfile;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function findOneByBooking(Booking $booking): ?Payment
    {
        return $this->findOneBy(['booking' => $booking]);
    }

    /**
     * Retrouve un paiement à partir de la référence du prestataire (pour Stripe,
     * l'identifiant de la session Checkout mémorisé au démarrage du paiement).
     */
    public function findOneByReference(string $reference): ?Payment
    {
        return $this->findOneBy(['reference' => $reference]);
    }

    /** Paiement en ligne retrouvé par sa session Stripe ou sa commande PayPal. */
    public function findOneByExternalId(string $externalId): ?Payment
    {
        return '' !== $externalId ? $this->findOneBy(['externalId' => $externalId]) : null;
    }

    /** Dernier numéro de facture attribué pour l'année (« FACT-2026-00042 »), ou null. */
    public function lastInvoiceNumber(int $year): ?string
    {
        $number = $this->createQueryBuilder('p')
            ->select('MAX(p.invoiceNumber)')
            ->andWhere('p.invoiceNumber LIKE :prefix')
            ->setParameter('prefix', sprintf('FACT-%d-%%', $year))
            ->getQuery()
            ->getSingleScalarResult();

        return \is_string($number) ? $number : null;
    }

    /**
     * Paiements des réservations d'un client, du plus récent au plus ancien
     * (écran « Paiements et abonnements » de l'espace compte).
     *
     * @return list<Payment>
     */
    public function findByClient(User $client): array
    {
        /** @var list<Payment> $payments */
        $payments = $this->createQueryBuilder('p')
            ->join('p.booking', 'b')
            ->addSelect('b')
            ->andWhere('b.client = :client')
            ->setParameter('client', $client->getId(), 'ulid')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $payments;
    }

    /**
     * Paiements des réservations d'un professionnel (« Revenus & Paiements »).
     *
     * @return list<Payment>
     */
    public function findForProvider(ProviderProfile $provider): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.booking', 'b')->addSelect('b')
            ->innerJoin('b.service', 's')->addSelect('s')
            ->innerJoin('b.client', 'c')->addSelect('c')
            ->andWhere('s.provider = :provider')
            ->setParameter('provider', $provider->getId(), 'ulid')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
