<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Catalog\Presenter\ActivityPresenter;
use App\Catalog\Repository\ServiceRepository;
use App\Catalog\StaticCatalog;
use App\Favorite\Service\CurrentUserFavorites;
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
    /** Valeur haute du curseur de budget, marquee « et + » sur la maquette. */
    private const PRICE_SLIDER_MAX = 1050;

    public function __construct(
        private readonly ServiceRepository $services,
        private readonly ActivityPresenter $presenter,
        private readonly CurrentUserFavorites $favorites,
    ) {
    }

    #[Route('/activites', name: 'app_activities')]
    public function index(Request $request): Response
    {
        // La barre de recherche de la maquette poste « q » et « lieu » en GET.
        // Jusqu'au 21/08 le contrôleur ne les lisait pas : on tapait un
        // mot-clé, on validait, et la page revenait identique.
        $keywords = trim((string) $request->query->get('q', ''));
        $place = trim((string) $request->query->get('lieu', ''));
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

        $searching = '' !== $keywords
            || '' !== $place
            || [] !== $categories
            || null !== $priceMin
            || null !== $priceMax
            || null !== $minRating;

        $activities = $this->presenter->cards(
            $this->services->findPublishedForListing(
                keywords: $keywords,
                place: $place,
                categorySlugs: $categories,
                priceMin: $priceMin,
                priceMax: $priceMax,
                minRating: $minRating,
            ),
            favoriteSlugs: $this->favorites->activitySlugs(),
        );

        return $this->render('activity/index.html.twig', [
            'activities' => $activities,
            // Les champs doivent afficher ce qui a été cherché : sinon la barre
            // se réinitialise et l'on ne sait plus ce qui a produit la liste.
            'q' => $keywords,
            'lieu' => $place,
            'categorie' => $categorie,
            'categories' => $categories,
            'prixMin' => $priceMin,
            'prixMax' => $priceMax,
            'note' => $minRating,
            'searching' => $searching,
            // Le panneau reste ouvert apres un envoi, sinon la personne perd
            // de vue les filtres qui ont produit la liste. Le marqueur est
            // explicite : une recherche depuis la barre du haut ne doit pas
            // ouvrir le panneau.
            'panneauOuvert' => $request->query->getBoolean('panneau'),
            // Rangée 3 de la maquette = répétition des cartes 5 à 8. Cette
            // répétition est un remplissage de maquette : sur un résultat de
            // recherche elle afficherait deux fois les mêmes activités, ce qui
            // ferait croire à un doublon. On ne la garde que hors recherche.
            'gridActivities' => $searching
                ? $activities
                : array_merge($activities, \array_slice($activities, 4, 4)),
            'offers' => StaticCatalog::offers(),
            'selections' => StaticCatalog::selections(),
            'cities' => StaticCatalog::cities(),
            'filterChips' => StaticCatalog::filterChips(),
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
    private function readMinRating(Request $request): ?float
    {
        $valeurs = array_filter(array_map('intval', $request->query->all('note')));

        return [] !== $valeurs ? (float) min($valeurs) : null;
    }

    #[Route('/activites/{slug}', name: 'app_activity_show')]
    public function show(string $slug): Response
    {
        $service = $this->services->findPublishedBySlug($slug);

        if (null === $service) {
            throw $this->createNotFoundException(sprintf('Activité « %s » introuvable.', $slug));
        }

        $detail = $this->presenter->detail($service);

        if (null === $detail) {
            // Une activite publiee sans contenu editorial afficherait une page
            // a moitie vide. Mieux vaut un 404 franc, qui se voit.
            throw $this->createNotFoundException(sprintf("L'activite « %s » n'a pas de fiche detaillee.", $slug));
        }

        return $this->render('activity/show.html.twig', [
            'activity' => $this->presenter->card($service, favoriteSlugs: $this->favorites->activitySlugs()),
            'detail' => $detail,
            'reviews' => StaticCatalog::reviews(),
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
