<?php

declare(strict_types=1);

namespace App\Provider\Repository;

use App\Catalog\Entity\Category;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderStatus;
use App\Provider\Geo\FrenchCityCoordinates;
use App\Provider\Geo\HaversineDistance;
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
     * Recherche publique de professionnels vérifiés, par métier, ville et
     * rayon (§5, §9 du CDC).
     *
     * Le rayon n'est appliqué que si la ville recherchée est connue de
     * FrenchCityCoordinates ; sinon la recherche retombe sur la
     * correspondance texte habituelle (voir le commentaire de cette classe).
     * Dans ce cas, seuls les prestataires dont la ville est ELLE AUSSI connue
     * participent au tri par distance : les autres sont exclus plutôt
     * qu'inclus avec une distance devinée.
     *
     * @return list<ProviderProfile>
     */
    public function search(?Category $category, ?string $city, ?int $radiusKm = null): array
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

        $origin = null !== $radiusKm ? FrenchCityCoordinates::coordinatesFor($city) : null;

        if (null === $origin && null !== $city && '' !== trim($city)) {
            $qb->andWhere('LOWER(p.city) LIKE LOWER(:city)')->setParameter('city', '%'.trim($city).'%');
        }

        /** @var list<ProviderProfile> $results */
        $results = $qb->getQuery()->getResult();

        if (null !== $origin) {
            return $this->withinRadius($results, $origin, $radiusKm);
        }

        return $results;
    }

    /**
     * @param array{0: float, 1: float} $origin
     * @param list<ProviderProfile>     $providers
     *
     * @return list<ProviderProfile> triés du plus proche au plus loin
     */
    private function withinRadius(array $providers, array $origin, int $radiusKm): array
    {
        $withDistance = [];

        foreach ($providers as $provider) {
            $coordinates = FrenchCityCoordinates::coordinatesFor($provider->getCity());
            if (null === $coordinates) {
                continue;
            }

            $distance = HaversineDistance::betweenKm($origin[0], $origin[1], $coordinates[0], $coordinates[1]);
            if ($distance <= $radiusKm) {
                $withDistance[] = [$provider, $distance];
            }
        }

        usort($withDistance, static fn (array $a, array $b): int => $a[1] <=> $b[1]);

        return array_column($withDistance, 0);
    }
}
