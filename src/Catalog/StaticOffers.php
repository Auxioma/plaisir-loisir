<?php

declare(strict_types=1);

namespace App\Catalog;

/**
 * Données statiques du flow « Offres du moments » (3 écrans).
 *
 * Comme StaticCatalog / StaticDestinations : du contenu de maquette en dur
 * pour faire vivre le front en attendant le câblage Doctrine. Les typos de
 * la maquette sont corrigées (Annecy, Aix-en-Provence, Bien-être…).
 */
final class StaticOffers
{
    /**
     * Catégories populaires du landing : pastille pleine colorée + volume.
     *
     * @return list<array<string, string>>
     */
    public static function categories(): array
    {
        return [
            ['name' => 'Sports & Aventures', 'icon' => 'sports', 'color' => 'violet', 'count' => '10+ activités'],
            ['name' => 'Gastronomies', 'icon' => 'crafts', 'color' => 'gold', 'count' => '34+ activités'],
            ['name' => 'Bien-être', 'icon' => 'wellness', 'color' => 'green', 'count' => '26+ activités'],
            ['name' => 'Cultures & Découvertes', 'icon' => 'culture', 'color' => 'blue', 'count' => '55+ activités'],
            ['name' => 'En famille', 'icon' => 'family', 'color' => 'orange', 'count' => '10+ activités'],
            ['name' => 'Soirées & Évènements', 'icon' => 'flame', 'color' => 'rose', 'count' => '18+ activités'],
        ];
    }

    /**
     * « Offres exclusives pour vous » : les 4 offres de la maquette (Canoë, Massage, Montgolfière, Visite guidée).
     *
     * @return list<array<string, mixed>>
     */
    public static function exclusives(): array
    {
        return [
            [
                'place' => "Gorges de l'Ardèche",
                'title' => 'Descente en Canoë',
                'rating' => '4.8',
                'reviews' => 256,
                'discount' => -30,
                'oldPrice' => 32,
                'price' => 25,
                'remaining' => 92040,
                'image' => 'images/activities/canoe.jpg',
                'slug' => 'descente-en-canoe-gorges-ardeche',
            ],
            [
                'place' => 'Aix-en-provence',
                'title' => 'Massage relaxant',
                'rating' => '4.9',
                'reviews' => 178,
                'discount' => -25,
                'oldPrice' => 80,
                'price' => 60,
                'remaining' => 92040,
                'image' => 'images/offers/massage.jpg',
                'slug' => null,
            ],
            [
                'place' => 'Labyrinthe en Provence',
                'title' => 'Vol en montgolfière',
                'rating' => '4.7',
                'reviews' => 134,
                'discount' => -20,
                'oldPrice' => 225,
                'price' => 180,
                'remaining' => 92040,
                'image' => 'images/activities/montgolfiere.jpg',
                'slug' => 'vol-en-montgolfiere',
            ],
            [
                'place' => "Saint-Tropez, côte d'azur",
                'title' => 'Visite guidée',
                'rating' => '4.6',
                'reviews' => 312,
                'discount' => -15,
                'oldPrice' => 30,
                'price' => 25,
                'remaining' => 92040,
                'image' => 'images/home/act-musee.jpg',
                'slug' => null,
            ],
        ];
    }

    /**
     * « Dernière minutes » : compte à rebours remplacé par « aujourd'hui » /
     * « Demain » (en rouge), badge de réduction rouge.
     *
     * @return list<array<string, mixed>>
     */
    public static function lastMinute(): array
    {
        return [
            [
                'place' => 'Massif du Vercors',
                'title' => 'Location VTT électronique',
                'rating' => '4.8',
                'reviews' => 256,
                'discount' => -30,
                'oldPrice' => 40,
                'price' => 32,
                'ends' => "aujourd'hui",
                'image' => 'images/home/act-vtt.jpg',
                'slug' => 'location-vtt-electrique',
            ],
            [
                'place' => 'Paris, Île-de-France',
                'title' => 'Visite du Musée',
                'rating' => '4.9',
                'reviews' => 178,
                'discount' => -25,
                'oldPrice' => 25,
                'price' => 16,
                'ends' => "aujourd'hui",
                'image' => 'images/home/act-musee.jpg',
                'slug' => 'visite-du-musee',
            ],
            [
                'place' => 'Annecy, Haute-Savoie',
                'title' => 'Paddle sur le lac',
                'rating' => '4.7',
                'reviews' => 134,
                'discount' => -20,
                'oldPrice' => 225,
                'price' => 180,
                'ends' => 'Demain',
                'image' => 'images/offers/paddle.jpg',
                'slug' => null,
            ],
            [
                'place' => 'Aix-en-Provence',
                'title' => 'Atelier cuisine provençale',
                'rating' => '4.6',
                'reviews' => 312,
                'discount' => -20,
                'oldPrice' => 55,
                'price' => 44,
                'ends' => 'Demain',
                'image' => 'images/activities/cuisine.jpg',
                'slug' => 'atelier-cuisine-provencale',
            ],
        ];
    }

    /**
     * Grille du listing (écran 2) : la maquette répète 3× les 4 offres.
     *
     * @return list<array<string, mixed>>
     */
    public static function listing(): array
    {
        $base = self::exclusives();

        return array_merge($base, $base, $base);
    }
}
