<?php

declare(strict_types=1);

namespace App\Favorite\Repository;

use App\Catalog\Entity\Destination;
use App\Catalog\Entity\Service;
use App\Favorite\Entity\Favorite;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Favorite>
 */
class FavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Favorite::class);
    }

    /**
     * Les activités mises en favori, avec de quoi les afficher.
     *
     * Les jointures évitent que chaque carte reparte chercher sa formule, ses
     * médias et sa catégorie — même raison que pour le listing du catalogue.
     *
     * @return list<Service>
     */
    public function findServicesForUser(User $user): array
    {
        // La racine est Service, et non Favorite : Doctrine refuse de ne
        // selectionner que des entites jointes sans l'alias racine
        // (« Cannot select entity through identification variables »).
        /** @var list<Service> $results */
        $results = $this->getEntityManager()->createQueryBuilder()
            ->select('s', 'p', 'm', 'c')
            ->from(Service::class, 's')
            ->innerJoin(Favorite::class, 'f', Join::WITH, 'f.service = s')
            ->leftJoin('s.packages', 'p')
            ->leftJoin('s.media', 'm')
            ->leftJoin('s.category', 'c')
            ->andWhere('f.user = :user')
            ->andWhere('s.deletedAt IS NULL')
            // Le type est precise explicitement : hors de son entite racine,
            // Doctrine lie l'identifiant en base32 (« 01M0GNEX... ») alors que
            // PostgreSQL attend un UUID, et la requete echoue.
            ->setParameter('user', $user->getId(), 'ulid')
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $results;
    }

    /**
     * @return list<Destination>
     */
    public function findDestinationsForUser(User $user): array
    {
        /** @var list<Destination> $results */
        $results = $this->getEntityManager()->createQueryBuilder()
            ->select('d')
            ->from(Destination::class, 'd')
            ->innerJoin(Favorite::class, 'f', Join::WITH, 'f.destination = d')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user->getId(), 'ulid')
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $results;
    }

    /**
     * Les slugs mis en favori, pour colorer les coeurs d'une grille.
     *
     * Une seule requete pour toute la page, plutot qu'un test par carte.
     *
     * @return array{services: list<string>, destinations: list<string>}
     */
    public function findFavoriteSlugs(User $user): array
    {
        /** @var list<array{sslug: string|null, dslug: string|null}> $rows */
        $rows = $this->createQueryBuilder('f')
            ->select('s.slug AS sslug', 'd.slug AS dslug')
            ->leftJoin('f.service', 's')
            ->leftJoin('f.destination', 'd')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user->getId(), 'ulid')
            ->getQuery()
            ->getArrayResult();

        $services = [];
        $destinations = [];

        foreach ($rows as $row) {
            if (null !== $row['sslug']) {
                $services[] = $row['sslug'];
            }
            if (null !== $row['dslug']) {
                $destinations[] = $row['dslug'];
            }
        }

        return ['services' => $services, 'destinations' => $destinations];
    }

    public function findOneByUserAndService(User $user, Service $service): ?Favorite
    {
        return $this->findOneBy(['user' => $user, 'service' => $service]);
    }

    public function findOneByUserAndDestination(User $user, Destination $destination): ?Favorite
    {
        return $this->findOneBy(['user' => $user, 'destination' => $destination]);
    }
}
