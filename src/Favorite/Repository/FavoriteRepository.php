<?php

declare(strict_types=1);

namespace App\Favorite\Repository;

use App\Catalog\Entity\Destination;
use App\Catalog\Entity\Service;
use App\Favorite\Entity\Favorite;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Favorite>
 */
class FavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Favorite::class);
    }

    public function findOneByUserAndService(User $user, Service $service): ?Favorite
    {
        return $this->findOneBy(['user' => $user, 'service' => $service]);
    }

    public function findOneByUserAndDestination(User $user, Destination $destination): ?Favorite
    {
        return $this->findOneBy(['user' => $user, 'destination' => $destination]);
    }
}
