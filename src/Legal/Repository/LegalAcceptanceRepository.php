<?php

declare(strict_types=1);

namespace App\Legal\Repository;

use App\Legal\Entity\LegalAcceptance;
use App\Legal\Entity\LegalDocument;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LegalAcceptance>
 */
class LegalAcceptanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LegalAcceptance::class);
    }

    public function hasAccepted(User $user, LegalDocument $document): bool
    {
        return null !== $this->findOneBy(['user' => $user, 'document' => $document]);
    }

    /**
     * Historique complet des acceptations d'un utilisateur.
     *
     * C'est la réponse à fournir en cas de demande d'accès au titre du RGPD,
     * et la pièce à produire en cas de litige.
     *
     * @return list<LegalAcceptance>
     */
    public function findForUser(User $user): array
    {
        /** @var list<LegalAcceptance> $results */
        $results = $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.acceptedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $results;
    }
}
