<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Catalog\Presenter\ActivityPresenter;
use App\Catalog\Presenter\DestinationPresenter;
use App\Catalog\Repository\DestinationRepository;
use App\Catalog\Repository\ServiceRepository;
use App\Catalog\StaticCatalog;
use App\Catalog\StaticDestinations;
use App\Favorite\Service\CurrentUserFavorites;
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
    ) {
    }

    #[Route('/destinations', name: 'app_destinations')]
    public function index(): Response
    {
        return $this->render('destination/index.html.twig', [
            'categories' => StaticDestinations::popularCategories(),
            'mosaic' => StaticDestinations::mosaic(),
            'ideas' => StaticDestinations::ideas(),
            'destinations' => $this->destinationPresenter->cards(
                $this->destinations->findForListing(4),
                $this->favorites->destinationSlugs(),
            ),
        ]);
    }

    #[Route('/destinations/populaires', name: 'app_destinations_popular')]
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
            'activities' => $this->cityActivities(),
            'selections' => StaticCatalog::selections(),
            'cities' => StaticCatalog::cities(),
            'reviews' => StaticDestinations::travelerReviews(),
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
