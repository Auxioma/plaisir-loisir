<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Catalog\StaticCatalog;
use App\Catalog\StaticDestinations;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Parcours « Destinations » (7 écrans de la maquette).
 *
 *  - /destinations            : landing (hero, catégories, mosaïque, bannière)
 *  - /destinations/populaires : listing 16 destinations + filtres/popovers
 *  - /destinations/{ville}    : activités d'une ville (maquette : Lille)
 *
 * L'en-tête suit l'état de connexion, mais ce n'est plus au contrôleur de le
 * dire : navbar.html.twig le lit dans la session (câblage du 17/08). La
 * prévisualisation de dev « ?connecte=1 » y est conservée.
 */
final class DestinationController extends AbstractController
{
    #[Route('/destinations', name: 'app_destinations')]
    public function index(): Response
    {
        return $this->render('destination/index.html.twig', [
            'categories' => StaticDestinations::popularCategories(),
            'mosaic' => StaticDestinations::mosaic(),
            'ideas' => StaticDestinations::ideas(),
            'destinations' => array_slice(StaticDestinations::popular(), 0, 4),
        ]);
    }

    #[Route('/destinations/populaires', name: 'app_destinations_popular')]
    public function popular(): Response
    {
        return $this->render('destination/populaires.html.twig', [
            'destinations' => StaticDestinations::popular(),
            'gastronomy' => StaticDestinations::gastronomy(),
            'selections' => StaticCatalog::selections(),
            'cities' => StaticCatalog::cities(),
            'reviews' => StaticDestinations::travelerReviews(),
        ]);
    }

    #[Route('/destinations/{ville}', name: 'app_destination_city', requirements: ['ville' => '(?!populaires$)[a-z0-9\-]+'])]
    public function city(string $ville): Response
    {
        if ('lille' !== $ville) {
            throw $this->createNotFoundException(sprintf('Destination « %s » inconnue.', $ville));
        }

        return $this->render('destination/ville.html.twig', [
            'city' => 'Lille',
            'activities' => StaticDestinations::cityActivities(),
            'selections' => StaticCatalog::selections(),
            'cities' => StaticCatalog::cities(),
            'reviews' => StaticDestinations::travelerReviews(),
        ]);
    }
}
