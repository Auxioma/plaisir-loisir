<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Availability\Repository\AvailabilityRepository;
use App\Booking\Controller\BookingController;
use App\Booking\Entity\Booking;
use App\Booking\Enum\BookingStatus;
use App\Catalog\Entity\Service;
use App\Catalog\Enum\ActivitySort;
use App\Catalog\Presenter\ActivityPresenter;
use App\Catalog\Repository\CategoryRepository;
use App\Catalog\Repository\PromotionRepository;
use App\Catalog\Repository\ServiceRepository;
use App\Catalog\StaticCatalog;
use App\Favorite\Service\CurrentUserFavorites;
use App\Review\Enum\ReviewStatus;
use App\Review\Repository\ReviewRepository;
use App\Stats\Service\PageViewRecorder;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Parcours « Activités » : listing (+ filtres et vue carte) et détail.
 *
 * CÂBLAGE DU LOT 2 : les cartes d'activités viennent désormais de la base
 * (entité Service), traduites en tableaux par ActivityPresenter pour que les
 * gabarits — calés au pixel — n'aient pas à changer.
 *
 * Ce qui reste dans StaticCatalog, et pourquoi :
 *  - `offers` : les offres à prix barré et leur compte à rebours relèvent du
 *    parcours Offres, qui a sa propre modélisation (lot à part).
 *  - `selections`, `cities`, `filterChips` : listes éditoriales de la maquette,
 *    sans entité correspondante à ce stade.
 *  - `reviews`, `suggestions` : les avis et les suggestions de fin de fiche
 *    relevent des entites Review et d'un moteur de recommandation, a venir.
 *
 * La fiche detaillee, elle, vient de la base depuis le 20/08 (ServiceDetail).
 *
 * Les routes sont publiques : aucune règle d'access_control ne couvre
 * /activites, donc l'accès est libre par défaut.
 */
final class ActivityController extends AbstractController
{
    /**
     * Douze activites par page : trois rangees de quatre, la grille de la
     * maquette.
     */
    private const PAR_PAGE = 12;

    /** Valeur haute du curseur de budget, marquee « et + » sur la maquette. */
    private const PRICE_SLIDER_MAX = 1050;

    public function __construct(
        private readonly ServiceRepository $services,
        private readonly ActivityPresenter $presenter,
        private readonly CurrentUserFavorites $favorites,
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    #[Route(path: ['fr' => '/activites', 'en' => '/en/activities'], name: 'app_activities')]
    public function index(Request $request, \App\PrivateActivity\Repository\PrivateActivityRepository $privateActivities): Response
    {
        // La barre de recherche de la maquette poste « q » et « lieu » en GET.
        // Jusqu'au 21/08 le contrôleur ne les lisait pas : on tapait un
        // mot-clé, on validait, et la page revenait identique.
        $keywords = trim((string) $request->query->get('q', ''));
        $place = trim((string) $request->query->get('lieu', ''));

        // Recherche de l'accueil (05/10), mode « Activités gratuites » sans
        // JavaScript : les résultats sont les activités entre membres.
        if ('gratuites' === $request->query->get('type')) {
            return $this->redirectToRoute('app_private_activities', array_filter([
                'lieu' => $place, 'q' => $keywords, 'date' => (string) $request->query->get('date', ''),
            ]));
        }
        // Les pastilles de categorie posent « categorie » dans l'URL : le
        // filtre se partage et survit au bouton Precedent. Le panneau lateral,
        // lui, coche plusieurs cases et envoie « categories[] ». Les deux
        // sources sont fondues en une seule liste.
        $categorie = trim((string) $request->query->get('categorie', ''));
        $categories = array_map('strval', $request->query->all('categories'));

        if ('' !== $categorie) {
            $categories[] = $categorie;
        }

        $categories = array_values(array_unique(array_filter($categories)));

        [$priceMin, $priceMax] = $this->readPriceRange($request);
        $minRating = $this->readMinRating($request);
        // La barre de recherche de l'accueil pose « date » et « participants ».
        // Jusqu'au 29/08 elle ne les posait pas du tout : on choisissait un jour
        // et un nombre de personnes, et la liste ne bougeait pas.
        $participants = $request->query->getInt('participants') ?: null;
        $date = $this->readDate($request);
        // Langue parlée (filtre de la page Explorer, 04/10).
        $language = trim((string) $request->query->get('langue', '')) ?: null;
        // Le tri et la page vivent dans l'URL, comme les filtres : une liste
        // triee se partage, se met en favori, et survit au bouton Precedent.
        $tri = ActivitySort::fromRequest($request->query->get('tri'));
        $page = max(1, $request->query->getInt('page', 1));

        $searching = '' !== $keywords
            || '' !== $place
            || [] !== $categories
            || null !== $priceMin
            || null !== $priceMax
            || null !== $minRating
            || null !== $participants
            || null !== $date
            || null !== $language;

        // DOUZE PAR PAGE : trois rangees de quatre, comme la maquette.
        $resultats = $this->services->paginateForListing(
            page: $page,
            perPage: self::PAR_PAGE,
            sort: $tri,
            keywords: $keywords,
            place: $place,
            categorySlugs: $categories,
            priceMin: $priceMin,
            priceMax: $priceMax,
            minRating: $minRating,
            participants: $participants,
            date: $date,
            language: $language,
        );

        $activities = $this->presenter->cards(
            $resultats['items'],
            favoriteSlugs: $this->favorites->activitySlugs(),
        );

        // Mode « Toutes » de l'accueil : on signale aussi les activités
        // gratuites entre membres qui répondent à la même recherche.
        $freeMatches = null;
        if ('toutes' === $request->query->get('type')) {
            $freeMatches = \count($privateActivities->findUpcomingDiscoverable($this->isGranted('ROLE_USER'), null, $place, $keywords, $date));
        }

        return $this->render('activity/index.html.twig', [
            'activities' => $activities,
            'free_matches' => $freeMatches,
            'free_params' => array_filter(['lieu' => $place, 'q' => $keywords, 'date' => $date?->format('Y-m-d')]),
            // Les champs doivent afficher ce qui a été cherché : sinon la barre
            // se réinitialise et l'on ne sait plus ce qui a produit la liste.
            'q' => $keywords,
            'lieu' => $place,
            'categorie' => $categorie,
            'categories' => $categories,
            'prixMin' => $priceMin,
            'prixMax' => $priceMax,
            'note' => $minRating,
            'participants' => $participants,
            'date' => $date,
            'searching' => $searching,
            // Le panneau reste ouvert apres un envoi, sinon la personne perd
            // de vue les filtres qui ont produit la liste. Le marqueur est
            // explicite : une recherche depuis la barre du haut ne doit pas
            // ouvrir le panneau.
            'panneauOuvert' => $request->query->getBoolean('panneau'),
            'tri' => $tri,
            'tris' => ActivitySort::ordered(),
            // Tout ce dont la page a besoin pour se paginer elle-meme.
            'total' => $resultats['total'],
            'page' => $resultats['page'],
            'pages' => $resultats['pages'],
            // LA REPETITION DES CARTES 5 A 8 EST SUPPRIMEE (01/09).
            // La rangee 3 de la maquette repete les cartes 5 a 8 pour remplir
            // trois rangees : c'etait un remplissage de planche, admissible
            // tant que la page montrait tout le catalogue d'un bloc. Avec une
            // pagination reelle, la page 1 aurait affiche douze cartes dont
            // quatre en double, et la page 2 aurait recommence a la neuvieme :
            // le visiteur aurait vu des doublons ET cru en avoir vu douze.
            'gridActivities' => $activities,
            'offers' => StaticCatalog::offers(),
            'selections' => StaticCatalog::selections(),
            'cities' => StaticCatalog::cities(),
            'filterChips' => StaticCatalog::filterChips(),
            // Catégories réelles du catalogue : onglets et cases à cocher de
            // la maquette activites.jpeg (01/10).
            'catalogCategories' => $this->categoryRepository->findRoots(),
            'clusters' => StaticCatalog::mapClusters(),
        ]);
    }

    /**
     * Fourchette de prix du panneau lateral.
     *
     * Le curseur haut est a 1050 dans la maquette, avec la mention
     * « 1050 EUR et + » : a cette valeur il n'exprime aucune limite, on ne
     * filtre donc pas par le haut. Sans cela, une activite a 1200 EUR serait
     * exclue alors que l'ecran annonce l'inverse.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function readPriceRange(Request $request): array
    {
        $min = $request->query->has('prix_min') ? $request->query->getInt('prix_min') : null;
        $max = $request->query->has('prix_max') ? $request->query->getInt('prix_max') : null;

        if (null !== $min && $min <= 0) {
            $min = null;
        }

        if (null !== $max && $max >= self::PRICE_SLIDER_MAX) {
            $max = null;
        }

        return [$min, $max];
    }

    /**
     * Note minimale demandee.
     *
     * Les cases sont « n etoiles et plus » : en cocher plusieurs revient a
     * demander la plus permissive. On retient donc la PLUS BASSE, sinon
     * cocher « 3 et plus » puis « 4 et plus » retirerait des resultats que la
     * premiere case venait d'autoriser.
     */
    /**
     * Jour demande, au format AAAA-MM-JJ.
     *
     * Une saisie illisible est IGNOREE plutot que refusee : une adresse
     * partagee, tronquee ou bricolee a la main doit rendre le catalogue, pas
     * une erreur. Le format ISO est impose parce que c'est le seul qui ne se
     * lise pas de deux facons selon le pays.
     */
    private function readDate(Request $request): ?\DateTimeImmutable
    {
        $brut = trim((string) $request->query->get('date', ''));

        if ('' === $brut) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $brut);

        return false !== $date ? $date : null;
    }

    private function readMinRating(Request $request): ?float
    {
        $valeurs = array_filter(array_map('intval', $request->query->all('note')));

        return [] !== $valeurs ? (float) min($valeurs) : null;
    }

    /**
     * Données du panneau de réservation (03/10) : prix réellement facturé
     * (formule la moins chère), créneaux ouverts groupés par jour, ou à
     * défaut les horaires d'ouverture, et la sélection en cours (conservée
     * pendant la connexion d'un visiteur).
     *
     * @return array{price: float|null, capacity: int|null, slots: array<string, list<array{time: string, remaining: int}>>, times: list<string>, draft: array<string, mixed>}
     */
    private function bookingPanel(Service $service, Request $request, AvailabilityRepository $availabilities): array
    {
        $price = null;
        $packages = [];
        foreach ($service->getPackages() as $package) {
            $price = null === $price ? (float) $package->getPrice() : min($price, (float) $package->getPrice());
            $packages[] = ['id' => (string) $package->getId(), 'name' => $package->getName(), 'description' => $package->getDescription(), 'price' => (float) $package->getPrice(), 'unit' => $package->getPricingUnit()->value];
        }
        usort($packages, static fn (array $a, array $b): int => $a['price'] <=> $b['price']);

        $slots = [];
        foreach ($availabilities->findUpcomingByService($service, new \DateTimeImmutable('+1 hour')) as $slot) {
            if ($slot->isBookable()) {
                $slots[$slot->getStartsAt()->format('Y-m-d')][] = ['time' => $slot->getStartsAt()->format('H:i'), 'remaining' => $slot->getRemainingSeats()];
            }
        }

        $draft = $request->hasSession() ? (array) $request->getSession()->get(BookingController::SESSION_KEY, []) : [];

        return [
            'price' => $price,
            'packages' => $packages,
            'cancellation' => $service->getCancellationPolicy(),
            'capacity' => $service->getCapacity(),
            'slots' => $slots,
            'times' => BookingController::DEFAULT_TIMES,
            'draft' => ($draft['slug'] ?? null) === $service->getSlug() ? $draft : [],
        ];
    }

    /**
     * Réservation terminée et pas encore notée du visiteur connecté : seule
     * une vraie participation ouvre le formulaire « Ajouter un avis ».
     */
    private function reviewableBooking(Service $service, ReviewRepository $reviewRepository, EntityManagerInterface $entityManager): ?Booking
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return null;
        }

        foreach ($entityManager->getRepository(Booking::class)->findBy(['client' => $user, 'service' => $service, 'status' => BookingStatus::Completed], ['startsAt' => 'DESC']) as $booking) {
            if (null === $reviewRepository->findOneBy(['booking' => $booking])) {
                return $booking;
            }
        }

        return null;
    }

    /**
     * Avis publiés sur l'activité, au format de la carte d'avis.
     *
     * @return list<array<string, mixed>>
     */
    private function reviewCards(Service $service, ReviewRepository $reviewRepository): array
    {
        $cards = [];
        foreach ($reviewRepository->findBy(['service' => $service, 'status' => ReviewStatus::Published], ['createdAt' => 'DESC']) as $review) {
            $author = $review->getAuthor();
            $comment = (string) $review->getComment();
            $cards[] = [
                'id' => (string) $review->getId(),
                'stars' => $review->getRating(),
                'title' => mb_strlen($comment) > 48 ? rtrim(mb_substr($comment, 0, 46)).'…' : ($comment ?: 'Avis vérifié'),
                'text' => $comment,
                'author' => trim($author?->getFirstName().' '.mb_substr((string) $author?->getLastName(), 0, 1).'.'),
                'meta' => $author?->getMainAddress()?->getCity() ?? 'Client vérifié',
                'date' => $review->getCreatedAt(),
                'avatar' => $author?->getAvatarPath() ?? 'images/account/avatar-default.svg',
                'reportable' => true,
                'reply' => $review->getProviderReply(),
            ];
        }

        return $cards;
    }

    #[Route(path: ['fr' => '/activites/{slug}', 'en' => '/en/activities/{slug}'], name: 'app_activity_show')]
    public function show(string $slug, Request $request, PageViewRecorder $pageViews, PromotionRepository $promotions, EntityManagerInterface $entityManager, AvailabilityRepository $availabilities, ReviewRepository $reviewRepository): Response
    {
        $service = $this->services->findPublishedBySlug($slug);

        if (null === $service) {
            throw $this->createNotFoundException(sprintf('Activité « %s » introuvable.', $slug));
        }

        // Statistiques de l'espace pro (02/10) : consultation de la fiche, et
        // offre en cours (affichée, comptée ; « clic » si l'on arrive par le
        // lien de l'offre, paramètre `offre`).
        $pageViews->recordActivity($request, $service, $this->getUser());
        $promotion = $promotions->findRunningForService($service);
        if (null !== $promotion && $this->getUser() !== $service->getProvider()?->getUser()) {
            $promotion->setViewsCount($promotion->getViewsCount() + 1);
            if ($request->query->get('offre') === (string) $promotion->getId()) {
                $promotion->setClicksCount($promotion->getClicksCount() + 1);
            }
            $entityManager->flush();
        }

        // Ce 404 pour « pas de fiche detaillee » est retire. Le raisonnement
        // etait : mieux vaut une absence franche qu'une page a moitie vide. Il
        // se tenait tant que les donnees venaient des fixtures, ou tout est
        // rempli. Confronte aux vraies donnees, il donnait ceci : le 24/08, les
        // QUATRE activites du catalogue en production menaient a une erreur. Un
        // visiteur voyait une carte, cliquait, tombait sur une page d'erreur.
        //
        // Une activite publiee doit avoir une page. Le presentateur la
        // construit desormais a partir de ce que l'activite sait d'elle-meme,
        // et le gabarit masque les blocs restes vides.
        $detail = $this->presenter->detail($service);

        return $this->render('activity/show.html.twig', [
            'activity' => $this->presenter->card($service, favoriteSlugs: $this->favorites->activitySlugs()),
            'detail' => $detail,
            'promotion' => $promotion,
            'booking' => $this->bookingPanel($service, $request, $availabilities),
            // Avis réels de l'activité (réservations terminées, 04/10) : les
            // avis figés de la maquette (StaticCatalog::reviews) ne sont plus
            // affichés — ils donnaient une note que personne n'a donnée.
            'reviews' => $this->reviewCards($service, $reviewRepository),
            'reviewable' => $this->reviewableBooking($service, $reviewRepository, $entityManager),
            // « Activites similaires » : la maquette y montrait deux activites
            // qui n'existent pas au catalogue, et surtout une premiere carte
            // qui renvoyait vers la page en cours de lecture. Ce sont
            // desormais de vraies activites, de la meme categorie en priorite.
            'suggestions' => $this->presenter->cards(
                $this->services->findSimilar($service),
                favoriteSlugs: $this->favorites->activitySlugs(),
            ),
        ]);
    }
}
