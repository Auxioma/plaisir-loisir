<?php

declare(strict_types=1);

namespace App\Controller;

use App\Catalog\StaticCatalog;
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
 * NB : front statique d'après la maquette — le câblage des vraies
 * données (catégories, destinations à la une) viendra ensuite.
 */
final class HomeController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.debug%')] private readonly bool $debug,
    ) {
    }

    #[Route('/', name: 'app_home')]
    public function index(Request $request): Response
    {
        $connected = null !== $this->getUser()
            || ($this->debug && $request->query->getBoolean('connecte'));

        // Depuis la génération de maquette du 21/07, visiteurs et connectés
        // partagent la même page ; seul l'en-tête change (S'inscrire vs avatar).
        return $this->render('home/connected.html.twig', [
            'activities' => array_values(StaticCatalog::activities()),
            'connected' => $connected,
        ]);
    }
}
