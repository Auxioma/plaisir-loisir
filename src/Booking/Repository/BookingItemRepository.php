<?php

declare(strict_types=1);

namespace App\Booking\Repository;

use App\Booking\Entity\BookingItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BookingItem>
 */
class BookingItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BookingItem::class);
    }
}
