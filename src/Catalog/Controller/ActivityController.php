<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Catalog\StaticCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Parcours « Activités » : listing (+ filtres et vue carte) et détail.
 *
 * Front statique d'après la maquette Figma — les données viennent de
 * StaticCatalog (source unique) en attendant le câblage Doctrine.
 * Les routes sont publiques : aucune règle d'access_control ne couvre
 * /activites, donc l'accès est libre par défaut.
 */
final class ActivityController extends AbstractController
{
    #[Route('/activites', name: 'app_activities')]
    public function index(): Response
    {
        $activities = array_values(StaticCatalog::activities());

        return $this->render('activity/index.html.twig', [
            'activities' => $activities,
            // Rangée 3 de la maquette = répétition des cartes 5 à 8.
            'gridActivities' => array_merge($activities, array_slice($activities, 4, 4)),
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
        $activity = StaticCatalog::activity($slug);
        if (null === $activity) {
            throw $this->createNotFoundException(sprintf('Activité « %s » introuvable.', $slug));
        }

        return $this->render('activity/show.html.twig', [
            'activity' => $activity,
            'detail' => StaticCatalog::detail($slug),
            'reviews' => StaticCatalog::reviews(),
            'suggestions' => StaticCatalog::suggestions(),
        ]);
    }
}
