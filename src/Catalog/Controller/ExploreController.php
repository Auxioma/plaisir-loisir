<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Catalog\Enum\ActivitySort;
use App\Catalog\Presenter\ActivityPresenter;
use App\Catalog\Repository\CategoryRepository;
use App\Catalog\Repository\ServiceRepository;
use App\Favorite\Service\CurrentUserFavorites;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Explorer » — maquette docs/maquettes/explorer.jpeg (04/10).
 *
 * Page d'inspiration : sélections thématiques, catégories, idées du moment
 * et carte « Autour de vous ». Le panneau de filtres envoie vers la
 * recherche d'activités (/activites), qui porte tous ces critères.
 */
final class ExploreController extends AbstractController
{
    /** Sélections thématiques : titre, accroche, image, icône, ton, paramètres de /activites. */
    private const COLLECTIONS = [
        ['Aventures en pleine nature', 'Randonnée, kayak, VTT et plus', 'images/gifts/card-kayak.jpg', 'tree', 'green', ['categories' => ['natures-plein-air', 'sports-aventures']]],
        ['Expériences incontournables', 'Nos coups de cœur du moment', 'images/events/ev-rando-clean.jpg', 'star', 'orange', ['tri' => 'note']],
        ['Escapades près de chez vous', 'Découvrez des pépites à proximité', 'images/offers/hero-offres.jpg', 'pin', 'blue', ['tri' => 'populaires']],
        ['Sorties & événements', 'Concerts, festivals, soirées…', 'images/gifts/fg-diner.jpg', 'party', 'violet', ['categories' => ['soirees-evenements']]],
        ['Bien-être & détente', 'Massages, yoga, spa…', 'images/gifts/tile-bienetre.jpg', 'leaf', 'green', ['categories' => ['bien-etre']]],
        ['En famille', 'Des moments à partager', 'images/gifts/dest-enfants.jpg', 'users', 'red', ['categories' => ['en-famille']]],
    ];

    private const CATEGORY_ICONS = [
        'sports-aventures' => 'cat_hiking', 'natures-plein-air' => 'tree', 'cultures-decouvertes' => 'cat_culture', 'bien-etre' => 'cat_wellness',
        'en-famille' => 'cat_family', 'ateliers-creations' => 'cat_crafts', 'soirees-evenements' => 'party', 'gastronomies' => 'utensils',
    ];

    #[Route(path: ['fr' => '/explorer', 'en' => '/en/explore'], name: 'app_explore')]
    public function index(ServiceRepository $services, CategoryRepository $categories, ActivityPresenter $presenter, CurrentUserFavorites $favorites): Response
    {
        $collections = [];
        foreach (self::COLLECTIONS as [$title, $subtitle, $image, $icon, $tone, $params]) {
            $count = $services->paginateForListing(1, 1, categorySlugs: $params['categories'] ?? [])['total'];
            if ($count > 0) {
                $collections[] = compact('title', 'subtitle', 'image', 'icon', 'tone', 'params', 'count');
            }
        }

        $cats = [];
        foreach ($categories->findBy(['parent' => null], ['position' => 'ASC']) as $category) {
            $cats[] = ['slug' => $category->getSlug(), 'name' => $category->getName(), 'icon' => self::CATEGORY_ICONS[$category->getSlug()] ?? 'grid'];
        }

        $all = $services->paginateForListing(1, 200, ActivitySort::Popular);
        $cards = $presenter->cards($all['items'], true, $favorites->activitySlugs());

        return $this->render('explore/index.html.twig', [
            'collections' => $collections,
            'categories' => $cats,
            'ideas' => \array_slice($cards, 0, 4),
            'nearby' => array_values(array_filter($cards, static fn (array $c): bool => null !== $c['lat'] && null !== $c['lng'])),
            'total' => $all['total'],
            'languages' => $services->distinctLanguages(),
        ]);
    }
}
