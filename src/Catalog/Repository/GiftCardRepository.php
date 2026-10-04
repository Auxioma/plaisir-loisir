<?php

declare(strict_types=1);

namespace App\Catalog\Repository;

use App\Catalog\Entity\GiftCard;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GiftCard>
 */
class GiftCardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GiftCard::class);
    }

    public function findOneByCheckoutReference(string $reference): ?GiftCard
    {
        return '' === $reference ? null : $this->findOneBy(['checkoutReference' => $reference]);
    }

    public function codeExists(string $code): bool
    {
        return null !== $this->findOneBy(['code' => $code]);
    }
}
