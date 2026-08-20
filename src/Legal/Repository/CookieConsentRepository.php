<?php

declare(strict_types=1);

namespace App\Legal\Repository;

use App\Legal\Entity\CookieConsent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CookieConsent>
 */
class CookieConsentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CookieConsent::class);
    }

    /**
     * Dernière décision connue pour ce navigateur.
     *
     * On trie sur la date de décision et non sur l'identifiant : deux lignes
     * peuvent être créées dans la même milliseconde lors d'un double clic.
     */
    public function findLatestForVisitor(string $visitorToken): ?CookieConsent
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.visitorToken = :token')
            ->setParameter('token', $visitorToken)
            ->orderBy('c.decidedAt', 'DESC')
            ->addOrderBy('c.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
