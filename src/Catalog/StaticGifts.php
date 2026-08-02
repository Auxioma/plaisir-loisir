<?php

declare(strict_types=1);

namespace App\Catalog;

/**
 * Catalogue statique du parcours « Bon cadeaux » (8 écrans de la maquette).
 *
 * Même principe que StaticCatalog/StaticDestinations : une seule source de
 * vérité en attendant le câblage Doctrine.
 *
 * NB maquette : les fautes du texte source sont corrigées ici
 * (« Atéliers » → Ateliers, « Ateleir/Ateleier » → Atelier, « cosmetique »
 * → cosmétique, « Procince » → Provence, « Louve » → Louvre, « paysage »
 * → paysages…) — même décision que pour les autres flows. Les titres
 * « Titre » sont les placeholders de la maquette, conservés tels quels.
 */
final class StaticGifts
{
    /**
     * Écran 1 : la mosaïque « Catégories populaires » / « Top catégories ».
     * Les tuiles sont les visuels de la maquette (badge + nom incrustés).
     *
     * @return list<array<string, string>>
     */
    public static function categories(): array
    {
        return [
            ['name' => 'Sports & Aventures — 158 activités', 'image' => 'images/gifts/tile-sports.jpg', 'span' => 'small'],
            ['name' => 'Bien-être — 150 activités', 'image' => 'images/gifts/tile-bienetre.jpg', 'span' => 'small'],
            ['name' => 'Cultures & Découvertes — 154 activités', 'image' => 'images/gifts/tile-cultures.jpg', 'span' => 'small'],
            ['name' => 'Ateliers & Créations — 59 activités', 'image' => 'images/gifts/tile-ateliers.jpg', 'span' => 'tall'],
            ['name' => 'Gastronomies — 241 activités', 'image' => 'images/gifts/tile-gastronomies.jpg', 'span' => 'wide'],
            ['name' => 'Visites guidées — 421 activités', 'image' => 'images/gifts/tile-visites.jpg', 'span' => 'wide'],
        ];
    }

    /**
     * Les 4 cartes cadeaux de base (taglines de la maquette, titre en
     * placeholder « Titre » comme sur Figma).
     *
     * @return list<array<string, mixed>>
     */
    private static function baseCards(): array
    {
        return [
            ['tagline' => 'Vivez des sensations fortes', 'title' => 'Titre', 'rating' => '4.8', 'reviews' => '256 reviews', 'price' => 25, 'badge' => 'Bestseller', 'image' => 'images/gifts/card-kayak.jpg'],
            ['tagline' => '01h de bien-être absolu', 'title' => 'Titre', 'rating' => '4.9', 'reviews' => '178', 'price' => 60, 'badge' => 'Nouveau', 'image' => 'images/gifts/card-cyclist.jpg'],
            ['tagline' => 'Survolez des paysages époustouflants', 'title' => 'Titre', 'rating' => '4.7', 'reviews' => '134 reviews', 'price' => 180, 'badge' => 'Tendance', 'image' => 'images/activities/montgolfiere.jpg'],
            ['tagline' => 'Histoire, art et patrimoine', 'title' => 'Titre', 'rating' => '4.6', 'reviews' => '312 reviews', 'price' => 16, 'badge' => 'Bestseller', 'image' => 'images/home/act-musee.jpg'],
        ];
    }

    /**
     * Écran 1 : la grille « + 20.000 activités partout en France » (4×2).
     *
     * @return list<array<string, mixed>>
     */
    public static function listing(): array
    {
        $base = self::baseCards();
        $hotel = ['tagline' => '2 Jours / 1 nuit en hôtel', 'title' => 'Titre', 'rating' => '4.8', 'reviews' => '256 reviews', 'price' => 120, 'badge' => 'Bestseller', 'image' => 'images/gifts/card-hotel.jpg'];

        return [...$base, $hotel, $base[1], $base[2], $base[3]];
    }

    /**
     * Écran 1 : la section « Meilleurs ventes » (4 cartes).
     *
     * @return list<array<string, mixed>>
     */
    public static function bestSellers(): array
    {
        return self::baseCards();
    }

    /**
     * Écrans 2-3 : les 8 cartes « Activités Ateliers & Créations »
     * (sous-titre rouge = nom d'atelier, typos maquette corrigées).
     *
     * @return list<array<string, mixed>>
     */
    public static function workshops(): array
    {
        $base = self::baseCards();
        $labels = [
            'Atelier céramique', 'Atelier de broderie', 'Atelier cosmétique', 'Atelier verre',
            'Atelier tufting', 'Atelier menuiserie', 'Atelier terrarium', 'Cours de pâtisserie',
        ];

        $cards = [];
        foreach ($labels as $i => $label) {
            $card = $base[$i % 4];
            $card['category'] = $label;
            unset($card['tagline']);
            $cards[] = $card;
        }

        return $cards;
    }

    /**
     * Écran 4 : la grille filtrée 3×4 (mode « lieu », avec les états
     * « image manquante » de la maquette : placeholder gris + icône).
     *
     * @return list<array<string, mixed>>
     */
    public static function filtered(): array
    {
        $mk = static fn (string $title, string $place, string $rating, int $reviews, string $duration, int $price, ?string $image): array => [
            'title' => $title,
            'place' => $place,
            'rating' => $rating,
            'reviews' => $reviews,
            'duration' => $duration,
            'price' => $price,
            'image' => $image ? 'images/gifts/'.$image : null,
        ];

        return [
            $mk('Dîner-croisière sur la Seine', 'Paris, Rouen', '4.8', 256, '2h-4h', 80, null),
            $mk('Titre', 'Paris', '4.9', 178, 'Journée', 135, 'fg-diner.jpg'),
            $mk('Dîner Immersif 4K dans la jungle - Jungle Palace', 'Paris', '4.5', 134, '1 jour', 50, null),
            $mk('Titre', 'Paris, France', '4.6', 312, '1.5h', 80, 'fg-craft.jpg'),
            $mk('Titre', "Provence-Alpes-Côte d'Azur", '4.8', 64, '2h30', 25, 'fg-cooking.jpg'),
            $mk('Titre', "Provence-Alpes-Côte d'Azur", '5.0', 93, '3h', 180, 'fg-choco.jpg'),
            $mk('Titre', 'Auvergne-Rhône-Alpes', '4.9', 37, '1h30', 25, 'fg-vins.jpg'),
            $mk('Titre', 'Lyon, Auvergne-Rhône-Alpes', '4.5', 68, '3h', 30, 'fg-charcuterie.jpg'),
            $mk('Titre', "Provence-Alpes-Côte d'Azur", '4.8', 64, '1 jour', 25, 'fg-croissants.jpg'),
            $mk('Titre', 'Paris, Seine', '5.0', 93, '3h', 40, 'fg-burger.jpg'),
            $mk('Cours de pâtisserie avec une grand-mère française', 'Paris, Louvre', '4.9', 37, '1h30', 25, null),
            $mk('Titre', 'Lyon, Auvergne-Rhône-Alpes', '4.5', 68, '3h', 45, 'fg-eclairs.jpg'),
        ];
    }

    /**
     * Écran 1 : « Trouver le cadeau par destinataires » (visuels maquette,
     * badge + nom incrustés).
     *
     * @return list<array<string, string>>
     */
    public static function recipients(): array
    {
        return [
            ['name' => 'Homme — 154 activités', 'image' => 'images/gifts/dest-homme.jpg'],
            ['name' => 'Femme — 158 activités', 'image' => 'images/gifts/dest-femme.jpg'],
            ['name' => 'Entreprise — 150 activités', 'image' => 'images/gifts/dest-entreprise.jpg'],
            ['name' => 'Enfants — 154 activités', 'image' => 'images/gifts/dest-enfants.jpg'],
        ];
    }

    /**
     * Écran 1 : les villes du carrousel « + 20.000 activités ».
     *
     * @return list<string>
     */
    public static function landingCities(): array
    {
        return ['Bordeaux', 'Paris', 'Toulouse', 'Reims', 'Annecy', 'Nice', 'Marseille', 'Grenoble', "Côte d'Azur", 'Dijon', 'Bordeaux', 'Nantes'];
    }

    /**
     * Écrans 2-4 : les chips de villes du listing.
     *
     * @return list<string>
     */
    public static function listingCities(): array
    {
        return ['Lille', 'Toulouse', 'Reims', 'Annecy', 'Nice', 'Marseille', 'Grenoble', "Côte d'Azur", 'Dijon', 'Bordeaux'];
    }
}
