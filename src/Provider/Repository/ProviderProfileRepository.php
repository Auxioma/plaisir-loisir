<?php

declare(strict_types=1);

namespace App\Provider\Repository;

use App\Provider\Entity\ProviderProfile;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProviderProfile>
 */
class ProviderProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProviderProfile::class);
    }

    public function findOneByUser(User $user): ?ProviderProfile
    {
        return $this->findOneBy(['user' => $user]);
    }
}
