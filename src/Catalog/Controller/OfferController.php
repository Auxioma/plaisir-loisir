<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Catalog\Repository\CategoryRepository;
use App\Catalog\Service\OfferCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Offres du moment » — maquette docs/maquettes/offres_moments.jpeg (04/10).
 *
 * Les offres sont les promotions en cours des professionnels (espace pro
 * « Offres & Promotions », modérées dans le back-office), et non plus les
 * cartes figées de StaticOffers. Les filtres de gauche passent par l'URL.
 */
final class OfferController extends AbstractController
{
    private const PER_PAGE = 8;

    /** Icônes des tuiles de catégories (slug de Category → icône). */
    private const CATEGORY_ICONS = [
        'sports-aventures' => ['cat_hiking', 'orange'], 'natures-plein-air' => ['tree', 'green'], 'bien-etre' => ['cat_wellness', 'green'],
        'cultures-decouvertes' => ['cat_culture', 'violet'], 'en-famille' => ['cat_family', 'red'], 'gastronomies' => ['utensils', 'yellow'],
        'soirees-evenements' => ['party', 'blue'], 'ateliers-creations' => ['cat_crafts', 'orange'],
    ];

    #[Route(path: ['fr' => '/offres', 'en' => '/en/deals'], name: 'app_offers')]
    public function index(Request $request, OfferCatalog $catalog, CategoryRepository $categories): Response
    {
        $q = $request->query;
        $filters = [
            'categorie' => (string) $q->get('categorie', ''),
            'prix' => max(0, min(500, (int) $q->get('prix', 500))),
            'reduction' => array_values(array_intersect(array_map('intval', $q->all('reduction')), [10, 20, 30, 40, 50])),
            'dispo' => array_values(array_intersect($q->all('dispo'), array_keys(OfferCatalog::AVAILABILITY))),
            'type' => array_values(array_intersect($q->all('type'), array_keys(OfferCatalog::TYPES))),
            'tri' => \array_key_exists((string) $q->get('tri'), OfferCatalog::SORTS) ? (string) $q->get('tri') : 'meilleures',
        ];
        $page = max(1, $q->getInt('page', 1));

        $all = $catalog->all();
        $filtered = $catalog->filter($all, $filters);
        // Les tuiles comptent les offres de chaque catégorie, autres filtres compris.
        $withoutCategory = $catalog->filter($all, ['categorie' => ''] + $filters);

        $tiles = [];
        foreach ($categories->findBy(['parent' => null], ['position' => 'ASC']) as $category) {
            $count = \count(array_filter($withoutCategory, static fn (array $r): bool => $r['categorySlug'] === $category->getSlug()));
            $tiles[] = ['slug' => $category->getSlug(), 'name' => $category->getName(), 'count' => $count, 'icon' => self::CATEGORY_ICONS[$category->getSlug()] ?? ['grid', 'blue']];
        }

        $byEnd = $catalog->filter($all, ['tri' => 'fin']);
        $flash = $catalog->filter($all, ['tri' => 'meilleures'])[0] ?? null;

        $params = array_filter([
            'categorie' => $filters['categorie'] ?: null,
            'prix' => $filters['prix'] < 500 ? $filters['prix'] : null,
            'reduction' => $filters['reduction'] ?: null,
            'dispo' => $filters['dispo'] ?: null,
            'type' => $filters['type'] ?: null,
            'tri' => 'meilleures' !== $filters['tri'] ? $filters['tri'] : null,
        ], static fn (mixed $v): bool => null !== $v);

        return $this->render('offer/index.html.twig', [
            'offers' => \array_slice($filtered, 0, $page * self::PER_PAGE),
            'total' => \count($filtered),
            'all_count' => \count($withoutCategory),
            'has_more' => \count($filtered) > $page * self::PER_PAGE,
            'page' => $page,
            'tiles' => $tiles,
            'last_minute' => \array_slice($byEnd, 0, 5),
            'flash' => $flash,
            'max_discount' => max([0, ...array_map(static fn (array $r): int => (int) ($r['discount'] ?? 0), $all)]),
            'filters' => $filters,
            'params' => $params,
            'types' => OfferCatalog::TYPES,
            'availability' => OfferCatalog::AVAILABILITY,
            'sorts' => OfferCatalog::SORTS,
        ]);
    }

    /** Ancienne liste complète : la page unique porte désormais filtres et pagination. */
    #[Route(path: ['fr' => '/offres/toutes', 'en' => '/en/deals/all'], name: 'app_offers_all')]
    public function all(Request $request): Response
    {
        return $this->redirectToRoute('app_offers', $request->query->all(), Response::HTTP_MOVED_PERMANENTLY);
    }
}
