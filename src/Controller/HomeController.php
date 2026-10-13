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
use App\PrivateActivity\Repository\PrivateActivityRepository;
use App\User\Entity\User;
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
        $members = $this->isGranted('ROLE_USER');

        // « Mettre en avant les activités proches de la ville de
        // l'utilisateur » (07/10) : adresse principale du membre. Les sorties de son département passent en
        // premier, les autres complètent la sélection.
        $city = $this->homeCity();
        $near = null !== $city ? $this->privateActivities->findUpcomingDiscoverable($members, place: $city['place'], limit: 8) : [];
        $others = array_filter(
            $this->privateActivities->findUpcomingDiscoverable($members, limit: 8 + \count($near)),
            static fn (PrivateActivity $a): bool => !\in_array($a, $near, true),
        );
        $free = \array_slice([...$near, ...$others], 0, 8);

        return $this->render('home/index.html.twig', [
            'featured' => $this->presenter->cards($featured['items'], true, $this->favorites->activitySlugs()),
            'activities_total' => $featured['total'],
            'free' => $free,
            'free_near_count' => \count($near),
            'home_city' => $city,
            'destinations' => $this->destinationPresenter->cards($this->destinations->findForListing(10), $this->favorites->destinationSlugs()),
            'search_places' => $this->services->distinctPlaces(),
            'search_titles' => $this->services->titlesForSearch(),
        ]);
    }

    /**
     * Ville de référence : l'adresse principale du membre connecté. Le champ
     * « Votre ville ou code postal » de l'accueil a été retiré (13/10, retour
     * client n° 21 : il ne faisait pas partie des fonctionnalités demandées).
     *
     * @return array{name: string, place: string}|null
     */
    private function homeCity(): ?array
    {
        $user = $this->getUser();
        $address = $user instanceof User ? ($user->getMainAddress() ?? $user->getAddresses()->first() ?: null) : null;
        if (null === $address || '' === trim($address->getCity())) {
            return null;
        }
        $postal = trim($address->getPostalCode());

        return ['name' => $address->getCity(), 'place' => preg_match('/^\d{5}$/', $postal) ? substr($postal, 0, 2) : $address->getCity()];
    }
}
