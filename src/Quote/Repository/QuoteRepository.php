<?php

declare(strict_types=1);

namespace App\Quote\Repository;

use App\Provider\Entity\ProviderProfile;
use App\Quote\Entity\Quote;
use App\Quote\Entity\ServiceRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Quote>
 */
class QuoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quote::class);
    }

    public function findOneByRequestAndProvider(ServiceRequest $request, ProviderProfile $provider): ?Quote
    {
        return $this->findOneBy(['serviceRequest' => $request, 'provider' => $provider]);
    }
}
