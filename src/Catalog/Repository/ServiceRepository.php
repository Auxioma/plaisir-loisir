<?php

declare(strict_types=1);

namespace App\Catalog\Repository;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\Destination;
use App\Catalog\Entity\Service;
use App\Catalog\Entity\ServicePackage;
use App\Catalog\Enum\ServiceStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
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
     * Les filtres sont ceux de la maquette : la barre de recherche du haut
     * (mots-clés, lieu) et le panneau latéral (catégories, budget, note).
     * Aucun n'était branché — ils s'affichaient sans rien filtrer.
     *
     * @param list<string> $categorySlugs
     *
     * @return list<Service>
     */
    public function findPublishedForListing(
        ?int $limit = null,
        ?string $keywords = null,
        ?string $place = null,
        array $categorySlugs = [],
        ?int $priceMin = null,
        ?int $priceMax = null,
        ?float $minRating = null,
    ): array {
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

        $categorySlugs = array_values(array_filter(array_map('trim', $categorySlugs)));

        if ([] !== $categorySlugs) {
            // Sur le slug et non sur le libelle : le libelle est du texte
            // d'affichage, il peut etre corrige sans casser les liens deja
            // partages.
            $qb->andWhere('c.slug IN (:categories)')
                ->setParameter('categories', $categorySlugs);
        }

        if (null !== $minRating) {
            $qb->andWhere('s.ratingAverage >= :note')
                ->setParameter('note', $minRating);
        }

        // Le prix vit sur les formules, pas sur l'activite : on passe par une
        // sous-requete plutot que par la jointure d'affichage `p`. Filtrer sur
        // `p` restreindrait AUSSI les formules chargees pour l'affichage, et
        // le prix montre sur la carte deviendrait le plus bas DANS la
        // fourchette au lieu du plus bas tout court.
        if (null !== $priceMin || null !== $priceMax) {
            $sous = $this->getEntityManager()->createQueryBuilder()
                ->select('IDENTITY(pf.service)')
                ->from(ServicePackage::class, 'pf');

            if (null !== $priceMin) {
                $sous->andWhere('pf.price >= :prixMin');
                $qb->setParameter('prixMin', (string) $priceMin);
            }

            if (null !== $priceMax) {
                $sous->andWhere('pf.price <= :prixMax');
                $qb->setParameter('prixMax', (string) $priceMax);
            }

            $qb->andWhere($qb->expr()->in('s.id', $sous->getDQL()));
        }

        if (null !== $limit) {
            // ATTENTION : avec les collections jointes ci-dessus, setMaxResults
            // limite les lignes SQL et non les entites. Aucun appel ne passe de
            // limite aujourd'hui ; le jour ou l'un le fera, il faudra passer
            // par Paginator, comme findSimilar().
            $qb->setMaxResults($limit);
        }

        /** @var list<Service> $results */
        $results = $qb->getQuery()->getResult();

        return $results;
    }

    /**
     * Les activites publiees rattachees a une destination.
     *
     * @return list<Service>
     */
    public function findPublishedForDestination(Destination $destination): array
    {
        /** @var list<Service> $results */
        $results = $this->createQueryBuilder('s')
            ->addSelect('p', 'm', 'c')
            ->leftJoin('s.packages', 'p')
            ->leftJoin('s.media', 'm')
            ->leftJoin('s.category', 'c')
            ->andWhere('s.destination = :destination')
            ->andWhere('s.status = :published')
            ->andWhere('s.deletedAt IS NULL')
            // Type precise explicitement : passer l'entite fait lier son
            // identifiant ULID en base32 (« 01M0HEZJ... ») alors que
            // PostgreSQL attend un UUID. Piege recurrent sur ce projet.
            ->setParameter('destination', $destination->getId(), 'ulid')
            ->setParameter('published', ServiceStatus::Published)
            ->orderBy('s.position', 'ASC')
            ->getQuery()
            ->getResult();

        return $results;
    }

    /**
     * Activites proches de celle qu'on consulte, pour le bloc de fin de fiche.
     *
     * Meme categorie d'abord, le reste du catalogue ensuite pour completer :
     * la maquette montre quatre cartes, et une categorie n'en contient pas
     * toujours quatre. Le tri se fait en une seule requete, par un CASE, plutot
     * qu'en deux appels suivis d'une fusion en PHP.
     *
     * L'activite courante est TOUJOURS exclue : la maquette proposait en
     * premiere suggestion un lien vers la page qu'on etait deja en train de
     * lire.
     *
     * @return list<Service>
     */
    public function findSimilar(Service $service, int $limit = 4): array
    {
        $qb = $this->createQueryBuilder('s')
            ->addSelect('p', 'm', 'c')
            ->leftJoin('s.packages', 'p')
            ->leftJoin('s.media', 'm')
            ->leftJoin('s.category', 'c')
            ->andWhere('s.status = :published')
            ->andWhere('s.deletedAt IS NULL')
            ->andWhere('s.id != :courante')
            ->setParameter('published', ServiceStatus::Published)
            ->setParameter('courante', $service->getId(), 'ulid')
            ->addOrderBy('s.position', 'ASC')
            ->setMaxResults($limit);

        $category = $service->getCategory();

        if (null !== $category) {
            $qb->addSelect('CASE WHEN c.id = :categorie THEN 0 ELSE 1 END AS HIDDEN memeCategorie')
                ->setParameter('categorie', $category->getId(), 'ulid')
                ->orderBy('memeCategorie', 'ASC')
                ->addOrderBy('s.position', 'ASC');
        }

        // Paginator est indispensable ici. Avec des collections jointes
        // (formules, medias), setMaxResults limite les LIGNES SQL et non les
        // entites : une activite qui porte six medias occupe six lignes, et
        // « quatre resultats » n'en ramenait qu'UNE SEULE. Paginator fait
        // d'abord une requete d'identifiants, puis charge ces entites-la.
        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: true);

        return array_values(iterator_to_array($paginator));
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

    /**
     * Lieux proposés pendant la frappe : « pa » doit faire apparaître Paris.
     *
     * Deux colonnes sont interrogées, et c'est voulu. `placeLabel` est ce que
     * la carte affiche — « Annecy, Haute-Savoie » — et c'est donc ce qu'on
     * reconnaît ; `city` est la ville seule, saisie parfois quand l'autre ne
     * l'est pas. Sans la seconde, une activité renseignée à moitié ne se
     * proposerait jamais.
     *
     * Le résultat est une liste de chaînes DISTINCTES : vingt activités à
     * Paris ne doivent pas donner vingt fois « Paris » dans la liste.
     *
     * @return list<string>
     */
    public function suggestPlaces(string $query, int $limit = 8): array
    {
        $query = mb_strtolower(trim($query));

        if ('' === $query) {
            return [];
        }

        $lignes = $this->createQueryBuilder('s')
            ->select('DISTINCT s.placeLabel AS place, s.city AS city')
            ->andWhere('s.status = :published')
            ->andWhere('s.deletedAt IS NULL')
            ->andWhere('LOWER(s.placeLabel) LIKE :q OR LOWER(s.city) LIKE :q')
            ->setParameter('published', ServiceStatus::Published)
            ->setParameter('q', '%'.$query.'%')
            ->setMaxResults($limit * 3)
            ->getQuery()
            ->getArrayResult();

        $libelles = [];

        foreach ($lignes as $ligne) {
            // On garde le libellé le plus parlant des deux, à condition qu'il
            // corresponde vraiment : une activité peut sortir sur sa ville
            // alors que son libellé de carte, lui, ne contient pas la saisie.
            foreach ([$ligne['place'], $ligne['city']] as $valeur) {
                if (\is_string($valeur) && '' !== $valeur && str_contains(mb_strtolower($valeur), $query)) {
                    $libelles[$valeur] = true;
                    break;
                }
            }
        }

        return \array_slice(array_keys($libelles), 0, $limit);
    }

    /**
     * Titres d'activités proposés pendant la frappe.
     *
     * @return list<array{label: string, slug: string}>
     */
    public function suggestTitles(string $query, int $limit = 8): array
    {
        $query = mb_strtolower(trim($query));

        if ('' === $query) {
            return [];
        }

        /** @var list<array{label: string, slug: string}> $lignes */
        $lignes = $this->createQueryBuilder('s')
            ->select('s.title AS label', 's.slug AS slug')
            ->andWhere('s.status = :published')
            ->andWhere('s.deletedAt IS NULL')
            ->andWhere('LOWER(s.title) LIKE :q')
            ->setParameter('published', ServiceStatus::Published)
            ->setParameter('q', '%'.$query.'%')
            ->orderBy('s.position', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return $lignes;
    }

    /**
     * Les lieux qui ont vraiment des activités, pour les panneaux de recherche.
     *
     * POURQUOI CETTE MÉTHODE EXISTE
     * Le panneau « Où voulez-vous aller ? » de l'accueil proposait les libellés
     * de la maquette — « Île-de-France », « La Côte d'Azur », « Toulouse ».
     * Aucun ne correspond au lieu d'une activité en base : choisir une de ces
     * réponses et lancer la recherche ne renvoyait donc rien. Un choix proposé
     * doit ramener au moins un résultat, sinon la recherche paraît cassée alors
     * qu'elle a parfaitement fonctionné.
     *
     * @return list<string>
     */
    public function distinctPlaces(int $limit = 9): array
    {
        /** @var list<array{place: string|null}> $lignes */
        $lignes = $this->createQueryBuilder('s')
            ->select('DISTINCT s.placeLabel AS place')
            ->andWhere('s.status = :published')
            ->andWhere('s.deletedAt IS NULL')
            ->andWhere('s.placeLabel IS NOT NULL')
            ->setParameter('published', ServiceStatus::Published)
            ->orderBy('s.placeLabel', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        $lieux = [];

        foreach ($lignes as $ligne) {
            if (\is_string($ligne['place']) && '' !== $ligne['place']) {
                $lieux[] = $ligne['place'];
            }
        }

        return $lieux;
    }

    /**
     * Les titres proposés dans le panneau « Activité ou loisir ? ».
     *
     * Même raison que distinctPlaces() : chaque proposition doit ramener au
     * moins l'activité qui porte ce titre.
     *
     * @return list<string>
     */
    public function titlesForSearch(int $limit = 9): array
    {
        /** @var list<array{title: string}> $lignes */
        $lignes = $this->createQueryBuilder('s')
            ->select('s.title AS title')
            ->andWhere('s.status = :published')
            ->andWhere('s.deletedAt IS NULL')
            ->setParameter('published', ServiceStatus::Published)
            ->orderBy('s.position', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_column($lignes, 'title');
    }
}
