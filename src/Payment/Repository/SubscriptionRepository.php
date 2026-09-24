<?php

declare(strict_types=1);

namespace App\Payment\Repository;

use App\Payment\Entity\Subscription;
use App\Payment\Enum\SubscriptionStatus;
use App\Provider\Entity\ProviderProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscription>
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    /**
     * L'abonnement en cours d'un prestataire : le plus récent qui ne soit pas
     * résilié. Un prestataire peut avoir plusieurs lignes dans le temps
     * (historique, voir le commentaire de l'entité) mais une seule à la fois
     * hors CANCELLED.
     */
    public function findCurrentFor(ProviderProfile $provider): ?Subscription
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.provider = :provider')
            ->andWhere('s.status != :cancelled')
            ->setParameter('provider', $provider->getId(), 'ulid')
            ->setParameter('cancelled', SubscriptionStatus::Cancelled)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByStripeSubscriptionId(string $stripeSubscriptionId): ?Subscription
    {
        return $this->findOneBy(['stripeSubscriptionId' => $stripeSubscriptionId]);
    }

    /**
     * @return list<Subscription>
     */
    public function findHistoryFor(ProviderProfile $provider): array
    {
        /** @var list<Subscription> $results */
        $results = $this->findBy(['provider' => $provider], ['createdAt' => 'DESC']);

        return $results;
    }
}
