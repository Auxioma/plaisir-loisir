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
 * Deux variantes d'après la maquette : l'accueil public (« Crée des
 * souvenirs ») et l'accueil connecté (hero kayak + recherche déployée).
 * La variante connectée s'affiche dès qu'un utilisateur est en session ;
 * en environnement de dev, « /?connecte=1 » permet de la prévisualiser
 * sans se connecter.
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
        $connected = $this->getUser() !== null
            || ($this->debug && $request->query->getBoolean('connecte'));

        if ($connected) {
            return $this->render('home/connected.html.twig', [
                'activities' => array_values(StaticCatalog::activities()),
            ]);
        }

        return $this->render('home/index.html.twig');
    }
}
