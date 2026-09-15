<?php

declare(strict_types=1);

namespace App\Provider\Controller;

use App\Catalog\Repository\CategoryRepository;
use App\Provider\Repository\ProviderProfileRepository;
use App\Review\Enum\ReviewStatus;
use App\Review\Repository\ReviewRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Recherche de professionnels et fiche publique (§5, §9 du CDC).
 *
 * Jusqu'ici la seule chose publiée sous /professionnels était une absence :
 * aucune route, donc aucun moyen pour un visiteur de chercher un prestataire
 * par métier ou par ville, alors que ServiceRequest et Quote — le cœur du
 * modèle demande/proposition — existaient déjà côté service.
 *
 * LA VILLE, PAS ENCORE LE RAYON
 * Le CDC demande métier/ville/rayon. Le rayon suppose un géocodage (adresse →
 * latitude/longitude) qu'aucun service du dépôt ne fournit aujourd'hui : la
 * recherche se limite donc à une correspondance texte sur la ville. Un vrai
 * rayon (Haversine sur lat/lng) est une extension immédiate de
 * ProviderProfileRepository::search() le jour où le géocodage existe.
 */
final class ProviderSearchController extends AbstractController
{
    public function __construct(
        private readonly ProviderProfileRepository $providers,
        private readonly CategoryRepository $categories,
        private readonly ReviewRepository $reviews,
    ) {
    }

    #[Route(path: ['fr' => '/professionnels', 'en' => '/en/professionals'], name: 'app_provider_search')]
    public function search(Request $request): Response
    {
        $categorySlug = (string) $request->query->get('metier', '');
        $city = trim((string) $request->query->get('ville', ''));

        $category = '' !== $categorySlug
            ? $this->categories->findOneBy(['slug' => $categorySlug])
            : null;

        $results = $this->providers->search($category, '' !== $city ? $city : null);

        return $this->render('provider/recherche.html.twig', [
            'results' => $results,
            'categories' => $this->categories->findRoots(),
            'selected_category' => $category,
            'city' => $city,
        ]);
    }

    #[Route(path: ['fr' => '/professionnels/{slug}', 'en' => '/en/professionals/{slug}'], name: 'app_provider_profile')]
    public function profile(string $slug): Response
    {
        $profile = $this->providers->findVerifiedBySlug($slug);

        if (null === $profile) {
            throw new NotFoundHttpException('Ce professionnel est introuvable.');
        }

        $reviews = array_values(array_filter(
            $this->reviews->findForProvider($profile),
            static fn ($review): bool => ReviewStatus::Published === $review->getStatus(),
        ));

        return $this->render('provider/profil_public.html.twig', [
            'provider' => $profile,
            'reviews' => $reviews,
            'average_rating' => $this->reviews->averageRatingForProvider($profile),
        ]);
    }
}
