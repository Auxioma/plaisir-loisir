<?php

declare(strict_types=1);

namespace App\Payment\Repository;

use App\Booking\Entity\Booking;
use App\Payment\Entity\Payment;
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
}
