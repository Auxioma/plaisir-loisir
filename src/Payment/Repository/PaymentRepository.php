<?php

declare(strict_types=1);

namespace App\Payment\Repository;

use App\Booking\Entity\Booking;
use App\Payment\Entity\Payment;
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
}
