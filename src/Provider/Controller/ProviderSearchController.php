<?php

declare(strict_types=1);

namespace App\Provider\Controller;

use App\Catalog\Repository\CategoryRepository;
use App\Provider\Repository\ProviderProfileRepository;
use App\Provider\Service\ProviderDirectory;
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
    public function search(Request $request, ProviderDirectory $directory): Response
    {
        $q = $request->query;
        $categorySlug = (string) $q->get('metier', '');
        $category = '' !== $categorySlug ? $this->categories->findOneBy(['slug' => $categorySlug]) : null;
        $filters = [
            'q' => trim((string) $q->get('q', '')),
            'category' => $category,
            'city' => trim((string) $q->get('ville', '')),
            'radius' => max(0, min(1000, $q->getInt('rayon'))),
            'rating' => \in_array((float) $q->get('note', 0), [3.0, 4.0, 4.5], true) ? (float) $q->get('note') : 0.0,
            'sort' => \array_key_exists((string) $q->get('tri'), ProviderDirectory::SORTS) ? (string) $q->get('tri') : 'pertinence',
        ];
        $results = $directory->search($filters);
        $page = max(1, $q->getInt('page', 1));
        $perPage = 12;

        $params = array_filter([
            'q' => $filters['q'] ?: null, 'metier' => $categorySlug ?: null, 'ville' => $filters['city'] ?: null,
            'rayon' => $filters['radius'] ?: null, 'note' => $filters['rating'] ?: null,
            'tri' => 'pertinence' !== $filters['sort'] ? $filters['sort'] : null,
        ], static fn (mixed $v): bool => null !== $v);

        return $this->render('provider/recherche.html.twig', [
            'results' => \array_slice($results, 0, $page * $perPage),
            'total' => \count($results),
            'has_more' => \count($results) > $page * $perPage,
            'page' => $page,
            'categories' => $this->categories->findRoots(),
            'selected_category' => $category,
            'filters' => $filters,
            'params' => $params,
            'sorts' => ProviderDirectory::SORTS,
            'markers' => array_values(array_filter(array_map(fn (array $r): ?array => null !== $r['lat'] ? [
                'lat' => $r['lat'], 'lng' => $r['lng'], 'title' => $r['name'], 'where' => $r['city'],
                'url' => $this->generateUrl('app_provider_profile', ['slug' => $r['slug']]),
            ] : null, $results))),
        ]);
    }

    #[Route(path: ['fr' => '/professionnels/{slug}', 'en' => '/en/professionals/{slug}'], name: 'app_provider_profile')]
    public function profile(string $slug, Request $request, PageViewRecorder $pageViews, ProviderDirectory $directory): Response
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
            'card' => $directory->card($profile),
            'reviews' => $reviews,
            'average_rating' => $this->reviews->averageRatingForProvider($profile),
        ]);
    }
}
