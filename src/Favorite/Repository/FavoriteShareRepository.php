<?php

declare(strict_types=1);

namespace App\Favorite\Repository;

use App\Favorite\Entity\FavoriteShare;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FavoriteShare>
 */
class FavoriteShareRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FavoriteShare::class);
    }

    public function findOneByToken(string $token): ?FavoriteShare
    {
        return $this->findOneBy(['token' => $token]);
    }
}
