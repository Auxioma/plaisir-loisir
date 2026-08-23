<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Catalog\StaticCatalog;
use App\Catalog\StaticDestinations;
use App\Catalog\StaticGifts;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Flow « Bon cadeaux » (8 écrans de la maquette).
 *
 * Écran 1 : landing /cadeaux. Écrans 2-3 : listing par catégorie. Écran 4 :
 * le même listing avec la sidebar de filtres (ancre #filtres, mécanique du
 * flow Destinations). Écrans 5-7 : « Vos informations » (les états vide /
 * erreur / rempli sont gérés côté client). Écran 8 : le paiement.
 */
final class GiftController extends AbstractController
{
    #[Route(path: ['fr' => '/cadeaux', 'en' => '/en/gift-cards'], name: 'app_gifts')]
    public function index(): Response
    {
        return $this->render('gift/index.html.twig', [
            'categories' => StaticGifts::categories(),
            'cards' => StaticGifts::listing(),
            'bestSellers' => StaticGifts::bestSellers(),
            'recipients' => StaticGifts::recipients(),
            'cities' => StaticGifts::landingCities(),
            'reviews' => StaticDestinations::travelerReviews(),
        ]);
    }

    #[Route(path: ['fr' => '/cadeaux/ateliers-creations', 'en' => '/en/gift-cards/workshops-and-crafts'], name: 'app_gifts_category')]
    public function category(): Response
    {
        return $this->render('gift/categorie.html.twig', [
            'workshops' => StaticGifts::workshops(),
            'filtered' => StaticGifts::filtered(),
            'categories' => StaticGifts::categories(),
            'cities' => StaticGifts::listingCities(),
            'selections' => StaticCatalog::selections(),
            'selectionCities' => StaticCatalog::cities(),
            'reviews' => StaticDestinations::travelerReviews(),
        ]);
    }

    #[Route(path: ['fr' => '/cadeaux/offrir', 'en' => '/en/gift-cards/buy'], name: 'app_gifts_offer')]
    public function offer(): Response
    {
        return $this->render('gift/offrir.html.twig');
    }

    #[Route(path: ['fr' => '/cadeaux/offrir/paiement', 'en' => '/en/gift-cards/buy/payment'], name: 'app_gifts_offer_payment')]
    public function payment(): Response
    {
        return $this->render('gift/paiement.html.twig');
    }
}
