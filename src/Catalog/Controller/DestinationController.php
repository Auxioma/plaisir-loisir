<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Catalog\Entity\Destination;
use App\Catalog\Presenter\ActivityPresenter;
use App\Catalog\Presenter\DestinationPresenter;
use App\Catalog\Repository\DestinationRepository;
use App\Catalog\Repository\ServiceRepository;
use App\Catalog\Entity\Service;
use App\Catalog\Service\CatalogShowcase;
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
 * TOUT VIENT DE LA BASE (06/11) : cartes, idées du moment, sélection par
 * catégorie, villes, pastilles et compteurs (CatalogShowcase). Les listes de
 * la maquette (Lille, Paris…, « 400+ résultats », « 88 activités ») sont
 * retirées, ainsi que l'écran de démonstration « Lille » et la maquette
 * /destinations/populaires, remplacée par le listing filtrable /destinations.
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
        private readonly CatalogShowcase $showcase,
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
            'ideas' => $this->showcase->ideas(),
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

    /**
     * L'ancienne maquette « Destinations populaires » (filtres et compteurs
     * simulés en JavaScript) : le listing /destinations fait la même chose
     * avec de vrais filtres. L'adresse reste valable pour les liens partagés.
     */
    #[Route(path: ['fr' => '/destinations/populaires', 'en' => '/en/destinations/popular'], name: 'app_destinations_popular')]
    public function popular(Request $request): Response
    {
        $search = trim((string) $request->query->get('q', '')) ?: trim((string) $request->query->get('lieu', ''));

        return $this->redirectToRoute('app_destinations', array_filter(['q' => $search]), Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route(path: ['fr' => '/destinations/{ville}', 'en' => '/en/destinations/{ville}'], name: 'app_destination_city', requirements: ['ville' => '(?!populaires$)[a-z0-9\-]+'])]
    public function city(string $ville, Request $request): Response
    {
        $destination = $this->destinations->findOneBySlug($ville);

        if (null === $destination) {
            throw $this->createNotFoundException(\sprintf('Destination « %s » inconnue.', $ville));
        }

        $keywords = trim((string) $request->query->get('q', ''));
        $place = trim((string) $request->query->get('lieu', ''));
        $category = trim((string) $request->query->get('categorie', ''));

        // Un autre lieu saisi dans la barre : on y va, plutôt que de le
        // chercher dans les activités de cette ville-ci.
        if ('' !== $place && Service::normalizeForSearch($place) !== Service::normalizeForSearch($destination->getName())) {
            $other = $this->destinations->searchByName($place, 1)[0] ?? null;

            return null !== $other
                ? $this->redirectToRoute('app_destination_city', array_filter(['ville' => $other->getSlug(), 'q' => $keywords]))
                : $this->redirectToRoute('app_activities', array_filter(['lieu' => $place, 'q' => $keywords]));
        }

        $all = $this->services->findPublishedForDestination($destination);
        // Les plus vues d'abord : c'est ce qu'annonce « Les plus populaires ».
        usort($all, static fn (Service $a, Service $b): int => $b->getViewsCount() <=> $a->getViewsCount());

        // Pastilles : les catégories réellement présentes dans cette ville.
        $chips = [];
        foreach ($all as $service) {
            if (null !== $c = $service->getCategory()) {
                $chips[$c->getSlug()] = $c->getName();
            }
        }

        $needle = Service::normalizeForSearch($keywords);
        $shown = array_values(array_filter($all, static fn (Service $s): bool => ('' === $category || $s->getCategory()?->getSlug() === $category)
            && ('' === $needle || str_contains((string) $s->getSearchText(), $needle))));

        return $this->render('destination/ville.html.twig', [
            'city' => $destination->getName(),
            // Le slug, et non le nom mis en minuscules : « Paris, France »
            // n'est pas une adresse valide.
            'citySlug' => $destination->getSlug(),
            'activities' => $this->activityPresenter->cards($shown, withCategory: true, favoriteSlugs: $this->favorites->activitySlugs()),
            'total' => \count($shown),
            'q' => $keywords,
            'categorie' => $category,
            'chips' => $chips,
            'suggestions' => array_values(array_filter($this->showcase->cities(10), static fn (array $c): bool => $c['slug'] !== $destination->getSlug())),
            'selections' => $this->showcase->selections(),
            'cities' => $this->showcase->cities(),
            'reviews' => $this->reviewPresenter->band(),
        ]);
    }
}
