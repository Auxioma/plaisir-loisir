<?php

declare(strict_types=1);

namespace App\Provider\Controller;

use App\Catalog\Repository\CategoryRepository;
use App\Provider\Repository\ProviderProfileRepository;
use App\Review\Enum\ReviewStatus;
use App\Review\Repository\ReviewRepository;
use App\Stats\Service\PageViewRecorder;
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
 * LE RAYON (Lot K, 16/09)
 * Le CDC demande métier/ville/rayon. Un vrai géocodage (adresse précise →
 * coordonnées) suppose un fournisseur externe — décision hors du périmètre
 * de ce câblage, comme OAuth ou Stripe. La recherche par rayon s'appuie donc
 * sur FrenchCityCoordinates, une table des principales villes françaises :
 * suffisant pour un rayon utile, sans clé d'API ni appel réseau. Voir le
 * commentaire de cette classe pour la limite assumée.
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
        $radiusKm = $request->query->getInt('rayon', 0);

        $category = '' !== $categorySlug
            ? $this->categories->findOneBy(['slug' => $categorySlug])
            : null;

        $results = $this->providers->search($category, '' !== $city ? $city : null, $radiusKm > 0 ? $radiusKm : null);

        return $this->render('provider/recherche.html.twig', [
            'results' => $results,
            'categories' => $this->categories->findRoots(),
            'selected_category' => $category,
            'city' => $city,
            'radius' => $radiusKm,
        ]);
    }

    #[Route(path: ['fr' => '/professionnels/{slug}', 'en' => '/en/professionals/{slug}'], name: 'app_provider_profile')]
    public function profile(string $slug, Request $request, PageViewRecorder $pageViews): Response
    {
        $profile = $this->providers->findVerifiedBySlug($slug);

        if (null === $profile) {
            throw new NotFoundHttpException('Ce professionnel est introuvable.');
        }

        // « Vues de profil » de l'espace pro (02/10).
        $pageViews->recordProfile($request, $profile, $this->getUser());

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
