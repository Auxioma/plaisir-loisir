<?php

declare(strict_types=1);

namespace App\Legal\Repository;

use App\Legal\Entity\CompanyIdentity;
use App\Provider\Entity\ProviderProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompanyIdentity>
 */
class CompanyIdentityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompanyIdentity::class);
    }

    public function findOneByProvider(ProviderProfile $profile): ?CompanyIdentity
    {
        return $this->findOneBy(['providerProfile' => $profile]);
    }

    public function findOneBySiret(string $siret): ?CompanyIdentity
    {
        return $this->findOneBy(['siret' => $siret]);
    }
}
