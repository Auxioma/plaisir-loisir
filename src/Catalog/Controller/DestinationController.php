<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Catalog\StaticCatalog;
use App\Catalog\StaticDestinations;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
 * L'en-tête suit l'état de connexion (variante invitée avec « S'inscrire »
 * sinon) ; en dev, « ?connecte=1 » force la variante connectée comme sur
 * l'accueil.
 */
final class DestinationController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.debug%')] private readonly bool $debug,
    ) {
    }

    #[Route('/destinations', name: 'app_destinations')]
    public function index(Request $request): Response
    {
        return $this->render('destination/index.html.twig', [
            'connected' => $this->isConnected($request),
            'categories' => StaticDestinations::popularCategories(),
            'mosaic' => StaticDestinations::mosaic(),
            'ideas' => StaticDestinations::ideas(),
            'destinations' => array_slice(StaticDestinations::popular(), 0, 4),
        ]);
    }

    #[Route('/destinations/populaires', name: 'app_destinations_popular')]
    public function popular(Request $request): Response
    {
        return $this->render('destination/populaires.html.twig', [
            'connected' => $this->isConnected($request),
            'destinations' => StaticDestinations::popular(),
            'gastronomy' => StaticDestinations::gastronomy(),
            'selections' => StaticCatalog::selections(),
            'cities' => StaticCatalog::cities(),
            'reviews' => StaticDestinations::travelerReviews(),
        ]);
    }

    #[Route('/destinations/{ville}', name: 'app_destination_city', requirements: ['ville' => '(?!populaires$)[a-z0-9\-]+'])]
    public function city(Request $request, string $ville): Response
    {
        if ('lille' !== $ville) {
            throw $this->createNotFoundException(sprintf('Destination « %s » inconnue.', $ville));
        }

        return $this->render('destination/ville.html.twig', [
            'connected' => $this->isConnected($request),
            'city' => 'Lille',
            'activities' => StaticDestinations::cityActivities(),
            'selections' => StaticCatalog::selections(),
            'cities' => StaticCatalog::cities(),
            'reviews' => StaticDestinations::travelerReviews(),
        ]);
    }

    private function isConnected(Request $request): bool
    {
        return null !== $this->getUser()
            || ($this->debug && $request->query->getBoolean('connecte'));
    }
}
