<?php

declare(strict_types=1);

namespace App\Controller;

use App\Catalog\Enum\ActivitySort;
use App\Catalog\Presenter\ActivityPresenter;
use App\Catalog\Presenter\DestinationPresenter;
use App\Catalog\Repository\DestinationRepository;
use App\Catalog\Repository\ServiceRepository;
use App\Favorite\Service\CurrentUserFavorites;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\ParticipationStatus;
use App\PrivateActivity\Repository\PrivateActivityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page d'accueil — maquette docs/maquettes/landing_page.jpeg (05/10).
 *
 * UNE SEULE page pour les visiteurs et les membres connectés. Jusqu'au 05/10
 * il y en avait deux (accueil « plateforme » pour les visiteurs, accueil
 * « Activités » une fois connecté) qui ne présentaient pas la même offre :
 * les activités gratuites n'apparaissaient qu'aux membres. La nouvelle
 * maquette réunit tout — activités des prestataires, activités gratuites
 * entre particuliers, photos — et seul l'en-tête change selon la session.
 * Une action réservée aux membres (favori, création, photos privées) mène à
 * la connexion, puis revient où l'on était.
 */
final class HomeController extends AbstractController
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly ActivityPresenter $presenter,
        private readonly CurrentUserFavorites $favorites,
        private readonly PrivateActivityRepository $privateActivities,
        private readonly DestinationRepository $destinations,
        private readonly DestinationPresenter $destinationPresenter,
    ) {
    }

    #[Route(path: ['fr' => '/', 'en' => '/en'], name: 'app_home')]
    public function index(): Response
    {
        $featured = $this->services->paginateForListing(1, 8, ActivitySort::Popular);

        $free = array_map(static fn (PrivateActivity $a): array => [
            'activity' => $a,
            'going' => \count(array_filter($a->getParticipations()->toArray(), static fn ($p): bool => ParticipationStatus::Accepted === $p->getStatus())),
        ], $this->privateActivities->findUpcomingDiscoverable($this->isGranted('ROLE_USER'), limit: 8));

        return $this->render('home/index.html.twig', [
            'featured' => $this->presenter->cards($featured['items'], true, $this->favorites->activitySlugs()),
            'activities_total' => $featured['total'],
            'free' => $free,
            'destinations' => $this->destinationPresenter->cards($this->destinations->findForListing(10), $this->favorites->destinationSlugs()),
            'search_places' => $this->services->distinctPlaces(),
            'search_titles' => $this->services->titlesForSearch(),
        ]);
    }
}
