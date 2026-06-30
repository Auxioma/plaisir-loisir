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
