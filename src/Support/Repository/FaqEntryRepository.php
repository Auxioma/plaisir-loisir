<?php

declare(strict_types=1);

namespace App\Support\Repository;

use App\Support\Entity\FaqEntry;
use App\Support\Enum\FaqCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FaqEntry>
 */
class FaqEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FaqEntry::class);
    }

    /**
     * Les questions publiées d'une langue, groupées par rubrique.
     *
     * Le regroupement se fait ici et non dans le gabarit : Twig saurait le
     * faire, mais la page a besoin des rubriques DANS L'ORDRE VOULU, y compris
     * celles qui n'ont aucune question — sinon le Centre d'aide afficherait
     * une grille à trous dès qu'une rubrique se vide.
     *
     * @return array<string, list<FaqEntry>> clés = valeurs de FaqCategory
     */
    public function publishedByCategory(string $locale = 'fr'): array
    {
        /** @var list<FaqEntry> $lignes */
        $lignes = $this->createQueryBuilder('f')
            ->andWhere('f.locale = :locale')
            ->andWhere('f.published = true')
            ->setParameter('locale', $locale)
            ->orderBy('f.position', 'ASC')
            ->addOrderBy('f.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        $groupes = [];

        foreach (FaqCategory::ordered() as $rubrique) {
            $groupes[$rubrique->value] = [];
        }

        foreach ($lignes as $ligne) {
            $groupes[$ligne->getCategory()->value][] = $ligne;
        }

        return $groupes;
    }

    /**
     * Les questions mises en avant, toutes rubriques confondues.
     *
     * @return list<FaqEntry>
     */
    public function featured(string $locale = 'fr', int $limit = 6): array
    {
        /** @var list<FaqEntry> $resultats */
        $resultats = $this->createQueryBuilder('f')
            ->andWhere('f.locale = :locale')
            ->andWhere('f.published = true')
            ->andWhere('f.featured = true')
            ->setParameter('locale', $locale)
            ->orderBy('f.position', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $resultats;
    }

    /**
     * Recherche plein texte sommaire sur la question et la réponse.
     *
     * Volontairement une simple recherche par occurrence : la FAQ compte
     * quelques dizaines de lignes, pas quelques milliers. Y mettre
     * Elasticsearch aujourd'hui coûterait plus cher que la page entière.
     *
     * @return list<FaqEntry>
     */
    public function search(string $terme, string $locale = 'fr', int $limit = 20): array
    {
        $terme = trim($terme);

        if ('' === $terme) {
            return [];
        }

        /** @var list<FaqEntry> $resultats */
        $resultats = $this->createQueryBuilder('f')
            ->andWhere('f.locale = :locale')
            ->andWhere('f.published = true')
            ->andWhere('LOWER(f.question) LIKE :terme OR LOWER(f.answer) LIKE :terme')
            ->setParameter('locale', $locale)
            ->setParameter('terme', '%'.mb_strtolower($terme).'%')
            ->orderBy('f.position', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $resultats;
    }

    public function countPublished(FaqCategory $category, string $locale = 'fr'): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.locale = :locale')
            ->andWhere('f.category = :category')
            ->andWhere('f.published = true')
            ->setParameter('locale', $locale)
            ->setParameter('category', $category)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
