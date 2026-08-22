<?php

declare(strict_types=1);

namespace App\User\Repository;

use App\User\Entity\SocialIdentity;
use App\User\Entity\User;
use App\User\Enum\SocialProvider;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SocialIdentity>
 */
class SocialIdentityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SocialIdentity::class);
    }

    /**
     * Retrouve une liaison par le couple (fournisseur, identifiant externe).
     *
     * C'est le SEUL point d'entrée légitime : rechercher par e-mail reviendrait
     * à faire confiance à une donnée que le fournisseur ne garantit pas.
     */
    public function findOneByExternal(SocialProvider $provider, string $externalId): ?SocialIdentity
    {
        return $this->findOneBy(['provider' => $provider, 'externalId' => $externalId]);
    }

    /**
     * @return list<SocialIdentity>
     */
    public function findForUser(User $user): array
    {
        /** @var list<SocialIdentity> $results */
        $results = $this->findBy(['user' => $user], ['createdAt' => 'ASC']);

        return $results;
    }

    public function findOneForUserAndProvider(User $user, SocialProvider $provider): ?SocialIdentity
    {
        return $this->findOneBy(['user' => $user, 'provider' => $provider]);
    }
}
