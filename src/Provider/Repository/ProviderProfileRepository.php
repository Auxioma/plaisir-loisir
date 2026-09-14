<?php

declare(strict_types=1);

namespace App\Provider\Repository;

use App\Catalog\Entity\Category;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderStatus;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProviderProfile>
 */
class ProviderProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProviderProfile::class);
    }

    public function findOneByUser(User $user): ?ProviderProfile
    {
        return $this->findOneBy(['user' => $user]);
    }

    /**
     * Double verrou (voir CLAUDE.md) : seul un dossier VÉRIFIÉ est consultable
     * publiquement, qu'importe l'exactitude du slug.
     */
    public function findVerifiedBySlug(string $slug): ?ProviderProfile
    {
        return $this->findOneBy(['slug' => $slug, 'status' => ProviderStatus::Verified]);
    }

    public function isSlugTaken(string $slug, ProviderProfile $except): bool
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.slug = :slug')
            ->setParameter('slug', $slug);

        // Comparaison par identifiant et non par objet : le profil qu'on
        // vérifie n'est pas toujours géré par l'EntityManager (cas d'un
        // profil encore en cours de création, avant le premier flush).
        if (null !== $except->getId()) {
            $qb->andWhere('p.id != :except')->setParameter('except', $except->getId(), 'ulid');
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Recherche publique de professionnels vérifiés, par métier et/ou ville.
     *
     * @return list<ProviderProfile>
     */
    public function search(?Category $category, ?string $city): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', ProviderStatus::Verified)
            ->orderBy('p.displayName', 'ASC');

        if (null !== $category) {
            // Type 'ulid' explicite : voir le commentaire de isSlugTaken() plus
            // haut, et ProviderDocumentRepository — passer l'entité ou son
            // identifiant nu à setParameter() sans préciser le type fait
            // parvenir le ULID brut à PostgreSQL, qui attend un UUID.
            $qb->andWhere('p.mainCategory = :category')->setParameter('category', $category->getId(), 'ulid');
        }

        if (null !== $city && '' !== trim($city)) {
            $qb->andWhere('LOWER(p.city) LIKE LOWER(:city)')->setParameter('city', '%'.trim($city).'%');
        }

        /** @var list<ProviderProfile> $results */
        $results = $qb->getQuery()->getResult();

        return $results;
    }
}
