<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Catalog\Presenter\ActivityPresenter;
use App\Catalog\Repository\ServiceRepository;
use App\Catalog\StaticCatalog;
use App\Favorite\Service\CurrentUserFavorites;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly ActivityPresenter $presenter,
        private readonly CurrentUserFavorites $favorites,
    ) {
    }

    #[Route('/activites', name: 'app_activities')]
    public function index(): Response
    {
        $activities = $this->presenter->cards(
            $this->services->findPublishedForListing(),
            favoriteSlugs: $this->favorites->activitySlugs(),
        );

        return $this->render('activity/index.html.twig', [
            'activities' => $activities,
            // Rangée 3 de la maquette = répétition des cartes 5 à 8.
            'gridActivities' => array_merge($activities, \array_slice($activities, 4, 4)),
            'offers' => StaticCatalog::offers(),
            'selections' => StaticCatalog::selections(),
            'cities' => StaticCatalog::cities(),
            'filterChips' => StaticCatalog::filterChips(),
            'clusters' => StaticCatalog::mapClusters(),
        ]);
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
            'suggestions' => StaticCatalog::suggestions(),
        ]);
    }
}
