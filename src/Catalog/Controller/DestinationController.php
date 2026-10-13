<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Catalog\Entity\Destination;
use App\Catalog\Presenter\ActivityPresenter;
use App\Catalog\Presenter\DestinationPresenter;
use App\Catalog\Repository\DestinationRepository;
use App\Catalog\Repository\ServiceRepository;
use App\Catalog\StaticCatalog;
use App\Catalog\StaticDestinations;
use App\Favorite\Service\CurrentUserFavorites;
use App\Review\Presenter\ReviewPresenter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Parcours « Destinations » (7 écrans de la maquette).
 *
 *  - /destinations            : landing (hero, catégories, mosaïque, bannière)
 *  - /destinations/populaires : listing 16 destinations + filtres/popovers
 *  - /destinations/{ville}    : activités d'une ville (maquette : Lille)
 *
 * CÂBLAGE DU LOT 2 : les cartes de destination et les activités de la page
 * ville viennent de la base ; les blocs éditoriaux restent dans
 * StaticDestinations, faute d'entité correspondante — mosaïque (une grille
 * CSS : colonnes, rangées, alignements), idées du moment, catégories, avis de
 * voyageurs, et la sélection « gastronomie », dont les activités n'existent
 * pas au catalogue.
 *
 * L'en-tête suit l'état de connexion, mais ce n'est pas au contrôleur de le
 * dire : navbar.html.twig le lit dans la session.
 */
final class DestinationController extends AbstractController
{
    public function __construct(
        private readonly DestinationRepository $destinations,
        private readonly ServiceRepository $services,
        private readonly DestinationPresenter $destinationPresenter,
        private readonly ActivityPresenter $activityPresenter,
        private readonly CurrentUserFavorites $favorites,
        private readonly ReviewPresenter $reviewPresenter,
    ) {
    }

    /** Continent de chaque pays (code ISO) : onglets de la maquette destinations.jpeg. */
    private const CONTINENTS = [
        'FR' => 'france',
        'IT' => 'europe', 'PT' => 'europe', 'DE' => 'europe', 'CZ' => 'europe', 'CH' => 'europe', 'ES' => 'europe', 'BE' => 'europe', 'GB' => 'europe', 'GR' => 'europe', 'NL' => 'europe', 'AT' => 'europe', 'IE' => 'europe', 'HR' => 'europe',
        'US' => 'amerique-nord', 'CA' => 'amerique-nord', 'MX' => 'amerique-nord',
        'BR' => 'amerique-sud', 'AR' => 'amerique-sud', 'PE' => 'amerique-sud', 'CL' => 'amerique-sud', 'CO' => 'amerique-sud',
        'MA' => 'afrique', 'EG' => 'afrique', 'SN' => 'afrique', 'TN' => 'afrique', 'ZA' => 'afrique', 'KE' => 'afrique', 'CI' => 'afrique',
        'ID' => 'asie', 'TH' => 'asie', 'JP' => 'asie', 'VN' => 'asie', 'CN' => 'asie', 'IN' => 'asie',
        'AU' => 'oceanie', 'NZ' => 'oceanie', 'PF' => 'oceanie', 'NC' => 'oceanie',
    ];

    private const PER_PAGE = 12;

    /** Seuils du filtre « Note des utilisateurs ». */
    private const RATINGS = [4.5, 4.0, 3.5];

    /** Type de destination, reconnu par mots-clés dans le nom, l'accroche et la description. */
    private const TYPES = [
        'villes' => ['ville', 'capitale', 'city', 'york', 'paris', 'marrakech', 'prague', 'lisbonne', 'rome', 'berlin', 'tokyo'],
        'montagne' => ['montagne', 'alpes', 'mont', 'sommet', 'suisse', 'ski'],
        'mer' => ['mer', 'plage', 'côte', 'cote', 'île', 'ile', 'bali', 'littoral', 'amalfitaine'],
        'campagne' => ['campagne', 'village', 'vignoble', 'nature', 'rizière', 'rizières'],
        'lacs' => ['lac', 'rivière', 'riviere', 'fleuve'],
    ];

    /**
     * Destinations — maquette docs/maquettes/destinations.jpeg (01/10) :
     * filtres réels (recherche, continent, type, budget, note) sur les
     * destinations publiées.
     */
    #[Route(path: ['fr' => '/destinations', 'en' => '/en/destinations'], name: 'app_destinations')]
    public function index(Request $request): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $continent = (string) $request->query->get('continent', '');
        $type = (string) $request->query->get('type', '');
        if (!isset(self::TYPES[$type])) {
            $type = '';
        }
        $budget = $request->query->has('budget') ? $request->query->getInt('budget') : null;
        $rating = $request->query->has('note') ? (float) $request->query->get('note') : null;
        $rating = \in_array($rating, self::RATINGS, true) ? $rating : null;
        $page = max(1, $request->query->getInt('page', 1));

        $all = $this->destinations->findForListing();
        // Le curseur de budget va jusqu'au prix le plus élevé du catalogue
        // (13/10, retour client n° 22) : avec un plafond fixe à 500 €, toutes
        // les destinations tenaient dans la première graduation et le filtre
        // semblait sans effet.
        $prices = array_filter(array_map(static fn (Destination $d): ?float => null !== $d->getPriceFrom() ? (float) $d->getPriceFrom() : null, $all));
        $budgetMax = max(50, (int) (ceil(([] !== $prices ? max($prices) : 50) / 10) * 10));
        if (null !== $budget && $budget >= $budgetMax) {
            $budget = null;
        }

        $filtered = array_values(array_filter($all, function (Destination $d) use ($query, $continent, $type, $budget, $rating): bool {
            $text = mb_strtolower($d->getName().' '.($d->getTagline() ?? '').' '.($d->getDescription() ?? '').' '.($d->getRegion() ?? ''));

            return ('' === $query || str_contains($text, mb_strtolower($query)))
                && ('' === $continent || (self::CONTINENTS[$d->getCountry()] ?? '') === $continent)
                && ('' === $type || self::matchesType($text, $type))
                && (null === $budget || (null !== $d->getPriceFrom() && (float) $d->getPriceFrom() <= $budget))
                && (null === $rating || (float) $d->getRatingAverage() >= $rating);
        }));

        // Toutes les destinations trouvées sont accessibles (« Voir plus »),
        // et plus seulement les douze premières.
        $shown = \array_slice($filtered, 0, self::PER_PAGE * $page);

        return $this->render('destination/index.html.twig', [
            'ideas' => StaticDestinations::ideas(),
            'destinations' => $this->destinationPresenter->cards($shown, $this->favorites->destinationSlugs()),
            'total' => \count($filtered),
            'has_more' => \count($filtered) > \count($shown),
            'page' => $page,
            'query' => $query,
            'continent' => $continent,
            'type' => $type,
            'types' => array_keys(self::TYPES),
            'budget' => $budget,
            'budget_max' => $budgetMax,
            'rating' => $rating,
            'ratings' => self::RATINGS,
            'filtering' => '' !== $query || '' !== $continent || '' !== $type || null !== $budget || null !== $rating,
        ]);
    }

    /** Mot-clé trouvé comme mot entier : « île » ne doit pas répondre à « ville ». */
    private static function matchesType(string $text, string $type): bool
    {
        foreach (self::TYPES[$type] as $keyword) {
            if (1 === preg_match('/(?<![\\p{L}])'.preg_quote($keyword, '/').'s?(?![\\p{L}])/u', $text)) {
                return true;
            }
        }

        return false;
    }

    #[Route(path: ['fr' => '/destinations/populaires', 'en' => '/en/destinations/popular'], name: 'app_destinations_popular')]
    public function popular(Request $request): Response
    {
        // Meme barre de recherche que le listing des activites, et meme
        // defaut jusqu'au 21/08 : les parametres partaient, personne ne les
        // lisait.
        $keywords = trim((string) $request->query->get('q', ''));
        $place = trim((string) $request->query->get('lieu', ''));
        // Les deux champs cherchent la meme chose ici : une destination EST un
        // lieu. Les separer n'aurait aucun sens.
        $search = '' !== $keywords ? $keywords : $place;
        $searching = '' !== $search;

        return $this->render('destination/populaires.html.twig', [
            'destinations' => $this->destinationPresenter->cards(
                $this->destinations->findForListing(keywords: $search),
                $this->favorites->destinationSlugs(),
            ),
            'q' => $keywords,
            'lieu' => $place,
            'searching' => $searching,
            'gastronomy' => StaticDestinations::gastronomy(),
            'selections' => StaticCatalog::selections(),
            'cities' => StaticCatalog::cities(),
            'reviews' => $this->reviewPresenter->band(),
        ]);
    }

    #[Route(path: ['fr' => '/destinations/{ville}', 'en' => '/en/destinations/{ville}'], name: 'app_destination_city', requirements: ['ville' => '(?!populaires$)[a-z0-9\-]+'])]
    public function city(string $ville): Response
    {
        // « lille » est la ville de la maquette : elle n'existe pas au
        // catalogue, mais c'est l'ecran qui a ete valide, avec ses douze
        // activites de demonstration. On le conserve tel quel.
        if ('lille' === $ville) {
            return $this->render('destination/ville.html.twig', [
                'city' => 'Lille',
                'citySlug' => 'lille',
                'activities' => $this->cityActivities(),
                'selections' => StaticCatalog::selections(),
                'cities' => StaticCatalog::cities(),
                'reviews' => $this->reviewPresenter->band(),
            ]);
        }

        $destination = $this->destinations->findOneBySlug($ville);

        if (null === $destination) {
            throw $this->createNotFoundException(sprintf('Destination « %s » inconnue.', $ville));
        }

        // Les activites rattachees a cette destination. AUCUNE ne l'est
        // aujourd'hui : le lien entre une activite et une destination n'a
        // jamais ete renseigne. La page s'affiche donc vide, ce qui est la
        // verite — et rend le manque visible, au lieu de montrer les activites
        // d'une autre ville comme le faisait le lien casse.
        return $this->render('destination/ville.html.twig', [
            'city' => $destination->getName(),
            // Le slug, et non le nom mis en minuscules : « Paris, France »
            // n'est pas une adresse valide, et le gabarit s'en servait pour
            // construire l'action de son formulaire de recherche.
            'citySlug' => $destination->getSlug(),
            'activities' => $this->activityPresenter->cards(
                $this->services->findPublishedForDestination($destination),
                withCategory: true,
                favoriteSlugs: $this->favorites->activitySlugs(),
            ),
            'selections' => StaticCatalog::selections(),
            'cities' => StaticCatalog::cities(),
            'reviews' => $this->reviewPresenter->band(),
        ]);
    }

    /**
     * Les douze cartes de la page ville.
     *
     * Les DONNÉES viennent de la base ; la COMPOSITION reste celle de la
     * maquette, qui affiche huit activités puis répète les quatre dernières
     * pour remplir la troisième rangée. C'est une mise en page, pas un contenu :
     * elle n'a rien à faire en base.
     *
     * Contrairement au listing général, la pastille de catégorie est affichée
     * ici — d'où `withCategory`.
     *
     * @return list<array<string, mixed>>
     */
    private function cityActivities(): array
    {
        $activities = $this->activityPresenter->cards(
            $this->services->findPublishedForListing(),
            withCategory: true,
            favoriteSlugs: $this->favorites->activitySlugs(),
        );

        $rowOne = \array_slice($activities, 0, 4);
        $rowTwo = \array_slice($activities, 4, 4);

        // La maquette pose un second « Bestseller » sur le yoga dans les
        // rangées 2 et 3, alors que la carte n'en porte pas ailleurs.
        foreach ($rowTwo as $index => $card) {
            if ('seance-de-yoga-en-pleine-nature' === $card['slug']) {
                $rowTwo[$index]['badge'] = 'Bestseller';
            }
        }

        return array_merge($rowOne, $rowTwo, $rowTwo);
    }
}
