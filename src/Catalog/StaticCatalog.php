<?php

declare(strict_types=1);

namespace App\Catalog;

/**
 * Catalogue statique du parcours « Activités ».
 *
 * Pourquoi cette classe ? La maquette exige que les MÊMES activités
 * (titre, note, avis, durée, prix) réapparaissent à l'identique sur
 * l'accueil, le listing, la vue carte et la page de détail. Plutôt que
 * de dupliquer ces données dans chaque template (risque de divergence),
 * on les centralise ici : une seule source de vérité.
 *
 * NB : c'est un socle provisoire pour le front. À la phase de câblage,
 * ces méthodes seront remplacées par des requêtes sur les entités
 * Doctrine (Service, ServicePackage, Review…) — les templates, eux,
 * ne changeront pas.
 */
final class StaticCatalog
{
    /**
     * Les 8 activités du listing (clé = slug d'URL).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function activities(): array
    {
        return [
            'descente-en-canoe' => [
                'slug' => 'descente-en-canoe',
                'place' => "Gorges de L'Ardèche",
                'title' => 'Descente en Canoë',
                'rating' => '4.8',
                'reviews' => 256,
                'duration' => '2h-3h',
                'price' => 25,
                'badge' => 'Bestseller',
                'image' => 'images/home/act-canoe.jpg',
            ],
            'location-vtt-electrique' => [
                'slug' => 'location-vtt-electrique',
                'place' => 'Massif du Vercors',
                'title' => 'Location VTT électrique',
                'rating' => '4.8',
                'reviews' => 178,
                'duration' => 'Journée',
                'price' => 45,
                'badge' => null,
                'image' => 'images/home/act-vtt.jpg',
            ],
            'visite-guidee-de-labyrinthe' => [
                'slug' => 'visite-guidee-de-labyrinthe',
                'place' => 'Labyrinthe en Provence',
                'title' => 'Visite guidée de labyrinthe',
                'rating' => '4.7',
                'reviews' => 134,
                'duration' => '1h30',
                'price' => 12,
                'badge' => null,
                'image' => 'images/home/act-labyrinthe.jpg',
            ],
            'visite-du-musee' => [
                'slug' => 'visite-du-musee',
                'place' => "Muséum d'Histoire Naturelle",
                'title' => 'Visite du Musée',
                'rating' => '4.8',
                'reviews' => 312,
                'duration' => '2h',
                'price' => 16,
                'badge' => null,
                'image' => 'images/home/act-musee.jpg',
            ],
            'atelier-cuisine-provencale' => [
                'slug' => 'atelier-cuisine-provencale',
                'place' => "Provence-Alpes-Côte d'Azur",
                'title' => 'Atelier cuisine provençale',
                'rating' => '4.8',
                'reviews' => 64,
                'duration' => '2h30',
                'price' => 25,
                'badge' => null,
                'image' => 'images/activities/cuisine.jpg',
            ],
            'vol-en-montgolfiere' => [
                'slug' => 'vol-en-montgolfiere',
                'place' => "Provence-Alpes-Côte d'Azur",
                'title' => 'Vol en montgolfière',
                'rating' => '5.0',
                'reviews' => 93,
                'duration' => '3h',
                'price' => 180,
                'badge' => null,
                'image' => 'images/activities/montgolfiere.jpg',
            ],
            'seance-de-yoga-en-pleine-nature' => [
                'slug' => 'seance-de-yoga-en-pleine-nature',
                'place' => 'Auvergne-Rhône-Alpes',
                'title' => 'Séance de yoga en pleine nature',
                'rating' => '4.9',
                'reviews' => 37,
                'duration' => '1h30',
                'price' => 25,
                'badge' => null,
                'image' => 'images/activities/yoga.jpg',
            ],
            'concert-live-soiree-musique' => [
                'slug' => 'concert-live-soiree-musique',
                'place' => 'Lyon, Auvergne-Rhône-Alpes',
                'title' => 'Concert live - Soirée musique',
                'rating' => '4.5',
                'reviews' => 68,
                'duration' => '3h',
                'price' => 30,
                'badge' => null,
                'image' => 'images/activities/soiree.jpg',
            ],
        ];
    }

    /** Une activité par son slug (ou null si inconnue). */
    public static function activity(string $slug): ?array
    {
        return self::activities()[$slug] ?? null;
    }

    /**
     * Les 4 offres exclusives (avec remise et compte à rebours).
     * `remaining` = secondes restantes affichées au chargement
     * (2 j 14 h 32 min sur la maquette), décomptées ensuite en JS.
     *
     * @return list<array<string, mixed>>
     */
    public static function offers(): array
    {
        $remaining = 2 * 86400 + 14 * 3600 + 32 * 60;

        return [
            [
                'place' => "Gorges de L'Ardèche",
                'title' => 'Descente en Canoë',
                'rating' => '4.8',
                'reviews' => 256,
                'discount' => -30,
                'oldPrice' => 32,
                'price' => 25,
                'remaining' => $remaining,
                'image' => 'images/home/act-canoe.jpg',
                'slug' => 'descente-en-canoe',
            ],
            [
                'place' => 'Aix-en-Provence',
                'title' => 'Massage relaxant',
                'rating' => '4.8',
                'reviews' => 89,
                'discount' => -25,
                'oldPrice' => 80,
                'price' => 60,
                'remaining' => $remaining,
                'image' => 'images/activities/massage.jpg',
                'slug' => null,
            ],
            [
                'place' => 'Labyrinthe en Provence',
                'title' => 'Vol en montgolfière',
                'rating' => '4.7',
                'reviews' => 93,
                'discount' => -20,
                'oldPrice' => 225,
                'price' => 180,
                'remaining' => $remaining,
                'image' => 'images/activities/montgolfiere.jpg',
                'slug' => 'vol-en-montgolfiere',
            ],
            [
                'place' => "Saint-Tropez, Côte d'Azur",
                'title' => 'Visite guidée',
                'rating' => '4.8',
                'reviews' => 121,
                'discount' => -15,
                'oldPrice' => 30,
                'price' => 25,
                'remaining' => $remaining,
                'image' => 'images/activities/visite-guidee.jpg',
                'slug' => null,
            ],
        ];
    }

    /**
     * « Votre sélection d'activités » : vignettes simples (titre + volume).
     *
     * @return list<array<string, mixed>>
     */
    public static function selections(): array
    {
        return [
            ['title' => 'Descente en Canoë', 'count' => 88, 'image' => 'images/home/act-canoe.jpg'],
            ['title' => 'Visite du Musée', 'count' => 254, 'image' => 'images/home/act-musee.jpg'],
            ['title' => 'Séance de yoga en pleine nature', 'count' => 45, 'image' => 'images/activities/yoga.jpg'],
            ['title' => 'Atelier cuisine provençale', 'count' => 157, 'image' => 'images/activities/cuisine.jpg'],
        ];
    }

    /**
     * Chips de villes de la section « Votre sélection d'activités »
     * (ordre et doublon « Bordeaux » fidèles à la maquette).
     *
     * @return list<string>
     */
    public static function cities(): array
    {
        return ['Paris', 'Bordeaux', 'Toulouse', 'Reims', 'Annecy', 'Nice', 'Marseille', 'Grenoble', "Côte d'Azur", 'Dijon', 'Bordeaux', 'Nantes'];
    }

    /**
     * Chips de catégories de la barre de filtres du listing.
     *
     * @return list<string>
     */
    public static function filterChips(): array
    {
        return ['Sports & Aventures', 'Toutes', 'Natures & Plein-air', 'Cultures & Découverte', 'Ateliers & Créations', 'Bien-être'];
    }
}
