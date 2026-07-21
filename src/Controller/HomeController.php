<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page d'accueil du site.
 *
 * NB : pour l'instant l'accueil est 100 % statique (front-end d'après la
 * maquette). Le branchement des vraies données (catégories, activités,
 * destinations à la une) se fera à la phase de câblage.
 */
final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }
}
