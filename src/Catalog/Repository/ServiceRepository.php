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
     * Les deux filtres sont ceux du formulaire de recherche de la maquette :
     * des mots-clés et un lieu. Sans eux, la barre de recherche renvoyait la
     * page identique — elle était affichée mais n'était branchée sur rien.
     *
     * @return list<Service>
     */
    public function findPublishedForListing(?int $limit = null, ?string $keywords = null, ?string $place = null, ?string $categorySlug = null): array
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

        $keywords = null !== $keywords ? trim($keywords) : '';

        if ('' !== $keywords) {
            // On compare deux textes passes par la MEME normalisation : sans
            // cela « canoe » ne trouverait jamais « Descente en Canoe » ecrit
            // avec un trema.
            $qb->andWhere('s.searchText LIKE :mots')
                ->setParameter('mots', '%'.Service::normalizeForSearch($keywords).'%');
        }

        $place = null !== $place ? trim($place) : '';

        if ('' !== $place) {
            // searchPlace rassemble le libelle affiche, la ville et la
            // destination : « ardeche » trouve « Gorges de L'Ardeche », que la
            // colonne `city` ne contient pas.
            $qb->andWhere('s.searchPlace LIKE :lieu')
                ->setParameter('lieu', '%'.Service::normalizeForSearch($place).'%');
        }

        $categorySlug = null !== $categorySlug ? trim($categorySlug) : '';

        if ('' !== $categorySlug) {
            // Sur le slug et non sur le libelle : le libelle est du texte
            // d'affichage, il peut etre corrige sans casser les liens deja
            // partages.
            $qb->andWhere('c.slug = :categorie')
                ->setParameter('categorie', $categorySlug);
        }

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
