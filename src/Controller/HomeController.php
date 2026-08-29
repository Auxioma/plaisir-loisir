<?php

declare(strict_types=1);

namespace App\Controller;

use App\Catalog\Presenter\ActivityPresenter;
use App\Catalog\Repository\ServiceRepository;
use App\Favorite\Service\CurrentUserFavorites;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page d'accueil du site.
 *
 * Une seule page (hero kayak + recherche déployée) pour visiteurs et
 * connectés — seule la navbar diffère. L'état connecté s'active dès
 * qu'un utilisateur est en session ; en environnement de dev,
 * « /?connecte=1 » permet de le prévisualiser sans se connecter.
 *
 * Les activités de l'accueil connecté viennent de la base depuis le lot 2 ;
 * les autres blocs (catégories, destinations à la une) suivront.
 */
final class HomeController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.debug%')] private readonly bool $debug,
        private readonly ServiceRepository $services,
        private readonly ActivityPresenter $presenter,
        private readonly CurrentUserFavorites $favorites,
    ) {
    }

    #[Route(path: ['fr' => '/', 'en' => '/en'], name: 'app_home')]
    public function index(Request $request): Response
    {
        $connected = null !== $this->getUser()
            || ($this->debug && $request->query->getBoolean('connecte'));

        // Deux pages distinctes (précision du 27/07) : l'accueil plateforme
        // (« Crée des souvenirs », navbar Découvrez/langue) pour les visiteurs,
        // et l'accueil Activités (hero kayak, navbar complète) une fois connecté.
        // Ici, l'état de connexion sert à choisir la PAGE, pas seulement
        // l'en-tête : ce sont deux écrans distincts de la maquette. La navbar,
        // elle, détermine son propre état à partir de la session.
        // Les deux panneaux de la barre de recherche sont alimentes par la
        // BASE et non par les libelles de la maquette. Ceux-ci — « Ile-de-France
        // », « La Cote d'Azur », « Toulouse » — ne correspondent au lieu
        // d'aucune activite : choisir l'une de ces reponses puis lancer la
        // recherche ne renvoyait aucun resultat, ce qui se lit comme une
        // recherche cassee alors qu'elle a parfaitement fonctionne. Une
        // proposition doit ramener au moins un resultat.
        $recherche = [
            'searchPlaces' => $this->services->distinctPlaces(),
            'searchActivities' => $this->services->titlesForSearch(),
        ];

        if ($connected) {
            return $this->render('home/connected.html.twig', [
                'activities' => $this->presenter->cards(
                    $this->services->findPublishedForListing(),
                    favoriteSlugs: $this->favorites->activitySlugs(),
                ),
                ...$recherche,
            ]);
        }

        return $this->render('home/index.html.twig', $recherche);
    }
}
