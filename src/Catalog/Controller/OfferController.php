<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Catalog\StaticOffers;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Flow « Offres du moments » (3 écrans de la maquette).
 *
 * Écran 1 : landing /offres (hero, catégories, offres exclusives, Vente
 * Flash, Dernière minutes, newsletter). Écran 2 : listing /offres/toutes.
 * Écran 3 : le listing avec la sidebar de filtres, ouverte côté client via
 * l'ancre #filtres (même mécanique que le flow Destinations).
 */
final class OfferController extends AbstractController
{
    #[Route('/offres', name: 'app_offers')]
    public function index(): Response
    {
        return $this->render('offer/index.html.twig', [
            'categories' => StaticOffers::categories(),
            'exclusives' => StaticOffers::exclusives(),
            'lastMinute' => StaticOffers::lastMinute(),
        ]);
    }

    #[Route('/offres/toutes', name: 'app_offers_all')]
    public function all(): Response
    {
        return $this->render('offer/toutes.html.twig', [
            'offers' => StaticOffers::listing(),
            'lastMinute' => StaticOffers::lastMinute(),
        ]);
    }
}
