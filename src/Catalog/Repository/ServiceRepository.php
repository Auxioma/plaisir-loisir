<?php

declare(strict_types=1);

namespace App\Catalog\Repository;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\Destination;
use App\Catalog\Entity\Service;
use App\Catalog\Enum\ServiceStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Service>
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    public function findOneBySlug(string $slug): ?Service
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * Les activités publiées, dans l'ordre d'affichage de la maquette.
     *
     * Les formules, les médias et la catégorie sont chargés dans la MÊME
     * requête. Sans ces jointures, afficher douze cartes déclencherait
     * trente-six requêtes supplémentaires — une par relation et par carte.
     *
     * `getResult()` renvoie ici des entités distinctes malgré les jointures :
     * Doctrine reconstruit les collections et ne duplique pas les racines.
     *
     * @return list<Service>
     */
    public function findPublishedForListing(?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->addSelect('p', 'm', 'c')
            ->leftJoin('s.packages', 'p')
            ->leftJoin('s.media', 'm')
            ->leftJoin('s.category', 'c')
            ->andWhere('s.status = :published')
            ->andWhere('s.deletedAt IS NULL')
            ->setParameter('published', ServiceStatus::Published)
            ->orderBy('s.position', 'ASC')
            ->addOrderBy('s.createdAt', 'ASC');

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        /** @var list<Service> $results */
        $results = $qb->getQuery()->getResult();

        return $results;
    }

    /**
     * Une activité publiée avec tout ce qu'il faut pour l'afficher.
     */
    public function findPublishedBySlug(string $slug): ?Service
    {
        return $this->createQueryBuilder('s')
            ->addSelect('p', 'm', 'c')
            ->leftJoin('s.packages', 'p')
            ->leftJoin('s.media', 'm')
            ->leftJoin('s.category', 'c')
            ->andWhere('s.slug = :slug')
            ->andWhere('s.status = :published')
            ->andWhere('s.deletedAt IS NULL')
            ->setParameter('slug', $slug)
            ->setParameter('published', ServiceStatus::Published)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param string[] $ids
     *
     * @return Service[]
     */
    public function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->findBy(['id' => $ids]);
    }

    /**
     * Recherche des activités publiées par mots-clés (titre/description/ville),
     * avec filtres optionnels par catégorie et destination.
     *
     * @return Service[]
     */
    public function searchPublished(string $query, ?Category $category = null, ?Destination $destination = null, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.status = :published')
            ->setParameter('published', ServiceStatus::Published);

        $query = trim($query);
        if ('' !== $query) {
            $qb->andWhere('LOWER(s.title) LIKE :q OR LOWER(s.description) LIKE :q OR LOWER(s.city) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower($query).'%');
        }

        if (null !== $category) {
            $qb->andWhere('s.category = :category')->setParameter('category', $category);
        }

        if (null !== $destination) {
            $qb->andWhere('s.destination = :destination')->setParameter('destination', $destination);
        }

        return $qb->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
