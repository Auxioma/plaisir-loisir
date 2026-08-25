<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Catalog\StaticCatalog;
use App\Catalog\StaticDestinations;
use App\Catalog\StaticGifts;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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

    /**
     * Écran 8 : le paiement.
     *
     * L'écran précédent postait ses champs en GET jusqu'au 25/08 : nom,
     * e-mail, téléphone, destinataire et message se retrouvaient dans
     * l'adresse de cette page. Il poste désormais, et le jeton est vérifié
     * ici comme sur les autres formulaires du site.
     *
     * La méthode GET reste acceptée : la page ne fait qu'afficher un écran
     * et l'interdire casserait l'actualisation et le bouton Précédent, sans
     * rien protéger — c'est le formulaire d'en face qui portait le défaut,
     * pas cette route.
     */
    #[Route(path: ['fr' => '/cadeaux/offrir/paiement', 'en' => '/en/gift-cards/buy/payment'], name: 'app_gifts_offer_payment', methods: ['GET', 'POST'])]
    public function payment(Request $request): Response
    {
        if ($request->isMethod('POST') && !$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de recommencer la saisie.');

            return $this->redirectToRoute('app_gifts_offer');
        }

        return $this->render('gift/paiement.html.twig');
    }
}
