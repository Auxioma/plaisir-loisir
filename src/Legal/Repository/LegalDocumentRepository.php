<?php

declare(strict_types=1);

namespace App\Legal\Repository;

use App\Legal\Entity\LegalDocument;
use App\Legal\Enum\LegalDocumentType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LegalDocument>
 */
class LegalDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LegalDocument::class);
    }

    /**
     * La version actuellement opposable d'un document.
     *
     * « Publiée ET entrée en vigueur ET la plus récente » : une version
     * annoncée mais dont la date d'effet est à venir ne doit pas remplacer
     * celle qui s'applique encore.
     *
     * Le paramètre :now est lié explicitement en datetimetz : les colonnes de
     * dates sont des « timestamp with time zone », et sans type explicite
     * Doctrine sérialise la valeur en « Y-m-d H:i:s » sans fuseau. PostgreSQL
     * la relit alors dans le fuseau de la session — ici Africa/Porto-Novo, PHP
     * étant en UTC — et décale la comparaison d'une heure. Résultat : un texte
     * publié « maintenant » sortait comme pas encore en vigueur et la page
     * retombait sur le message « en cours de rédaction ».
     */
    public function findCurrent(LegalDocumentType $type, string $locale = 'fr'): ?LegalDocument
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.type = :type')
            ->andWhere('d.locale = :locale')
            ->andWhere('d.publishedAt IS NOT NULL')
            ->andWhere('d.effectiveAt <= :now')
            ->setParameter('type', $type)
            ->setParameter('locale', $locale)
            ->setParameter('now', new \DateTimeImmutable(), Types::DATETIMETZ_IMMUTABLE)
            ->orderBy('d.effectiveAt', 'DESC')
            ->addOrderBy('d.publishedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Toutes les versions d'un document, de la plus récente à la plus ancienne.
     *
     * @return list<LegalDocument>
     */
    public function findHistory(LegalDocumentType $type, string $locale = 'fr'): array
    {
        /** @var list<LegalDocument> $results */
        $results = $this->createQueryBuilder('d')
            ->andWhere('d.type = :type')
            ->andWhere('d.locale = :locale')
            ->setParameter('type', $type)
            ->setParameter('locale', $locale)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $results;
    }

    public function findOneByVersion(LegalDocumentType $type, string $version, string $locale = 'fr'): ?LegalDocument
    {
        return $this->findOneBy(['type' => $type, 'version' => $version, 'locale' => $locale]);
    }
}
