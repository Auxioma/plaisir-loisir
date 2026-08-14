<?php

declare(strict_types=1);

namespace App\Catalog;

/**
 * Catalogue statique du parcours « Destinations » (7 écrans de la maquette).
 *
 * Même principe que StaticCatalog : une seule source de vérité pour que
 * les mêmes destinations/activités réapparaissent à l'identique d'un
 * écran à l'autre, en attendant le câblage Doctrine.
 *
 * NB maquette : les fautes du texte source sont corrigées ici
 * (« Provence-Alpes-Côte d'Azur », « montgolfière », « Louvre »,
 * « fromages », « activités »…) — décision actée avec la spec §10.
 */
final class StaticDestinations
{
    /**
     * Écran 2 : les 16 destinations populaires (grille 4×4).
     *
     * @return list<array<string, mixed>>
     */
    public static function popular(): array
    {
        $filler = static fn (string $name, string $image): array => [
            'name' => $name,
            'tagline' => 'Couleurs, saveurs et traditions',
            'rating' => '4.7',
            'reviews' => 189,
            'count' => 30,
            'price' => 22,
            'badge' => null,
            'favorite' => false,
            'image' => $image,
        ];

        return [
            ['name' => 'Paris, France', 'tagline' => 'Ville lumière et capitale de la culture', 'rating' => '4.8', 'reviews' => 256, 'count' => 32, 'price' => 25, 'badge' => 'Populaire', 'favorite' => false, 'image' => 'images/home/dest-paris.png'],
            ['name' => 'Annecy, France', 'tagline' => 'Entre lac et Montagnes', 'rating' => '4.3', 'reviews' => 178, 'count' => 24, 'price' => 20, 'badge' => 'Bestseller', 'favorite' => false, 'image' => 'images/home/dest-annecy.png'],
            ['name' => 'Cinque, Italie', 'tagline' => 'Vue à couper le souffle', 'rating' => '4.7', 'reviews' => 134, 'count' => 18, 'price' => 30, 'badge' => 'Tendance', 'favorite' => false, 'image' => 'images/home/dest-cinqueterre.jpg'],
            ['name' => 'Bali, Indonésie', 'tagline' => 'Détente, nature et spiritualité', 'rating' => '4.9', 'reviews' => 312, 'count' => 27, 'price' => 35, 'badge' => null, 'favorite' => false, 'image' => 'images/home/dest-bali.png'],
            ['name' => 'New York, USA', 'tagline' => 'La ville qui ne dort jamais', 'rating' => '4.8', 'reviews' => 412, 'count' => 31, 'price' => 40, 'badge' => null, 'favorite' => true, 'image' => 'images/destinations/dest-newyork.jpg'],
            $filler('Marrakech, Maroc', 'images/destinations/dest-marrakech.jpg'),
            $filler('Côte Amalfitaine, Italie', 'images/destinations/dest-amalfi.jpg'),
            $filler('Portugal', 'images/destinations/dest-portugal.jpg'),
            $filler('Thaïlande', 'images/destinations/dest-thailande.jpg'),
            $filler('Allemagne', 'images/destinations/dest-allemagne.jpg'),
            $filler('République tchèque', 'images/destinations/dest-tcheque.jpg'),
            $filler('Suisse', 'images/destinations/dest-suisse-1.jpg'),
            $filler('Suisse', 'images/destinations/dest-suisse-2.jpg'),
            $filler('Égypte', 'images/destinations/dest-egypte.jpg'),
            $filler('Canada', 'images/destinations/dest-canada.jpg'),
            $filler('Japon', 'images/destinations/dest-japon.jpg'),
        ];
    }

    /**
     * Écran 3 : les 12 activités de la page ville (mode « catégorie »),
     * réutilisant les activités du catalogue commun (mêmes fiches).
     *
     * @return list<array<string, mixed>>
     */
    public static function cityActivities(): array
    {
        $all = StaticCatalog::activities();
        $categories = [
            'descente-en-canoe' => 'Sports & Aventures',
            'location-vtt-electrique' => 'Sports & Aventures',
            'visite-guidee-de-labyrinthe' => 'Cultures & Découverte',
            'visite-du-musee' => 'Cultures & Découverte',
            'atelier-cuisine-provencale' => 'Ateliers & Créations',
            'vol-en-montgolfiere' => 'Sports & Aventures',
            'seance-de-yoga-en-pleine-nature' => 'Bien-être',
            'concert-live-soiree-musique' => 'Soirées & Évènements',
        ];

        $rowOne = ['descente-en-canoe', 'location-vtt-electrique', 'visite-guidee-de-labyrinthe', 'visite-du-musee'];
        $rowTwo = ['atelier-cuisine-provencale', 'vol-en-montgolfiere', 'seance-de-yoga-en-pleine-nature', 'concert-live-soiree-musique'];

        $cards = [];
        foreach ([...$rowOne, ...$rowTwo, ...$rowTwo] as $slug) {
            $a = $all[$slug];
            $a['category'] = $categories[$slug];
            // La maquette pose un 2e Bestseller sur le yoga (rangées 2-3).
            if ('seance-de-yoga-en-pleine-nature' === $slug) {
                $a['badge'] = 'Bestseller';
            }
            $cards[] = $a;
        }

        return $cards;
    }

    /**
     * Écran 5 : les 12 activités « Gastronomie » (mode « lieu »).
     *
     * @return list<array<string, mixed>>
     */
    public static function gastronomy(): array
    {
        $mk = static fn (string $title, string $place, string $rating, int $reviews, string $duration, int $price, ?string $badge, string $image): array => [
            'slug' => null,
            'title' => $title,
            'place' => $place,
            'rating' => $rating,
            'reviews' => $reviews,
            'duration' => $duration,
            'price' => $price,
            'badge' => $badge,
            'image' => 'images/destinations/'.$image,
        ];

        return [
            $mk('Dîner-croisière sur la Seine', 'Paris, Rouen', '4.8', 256, '2h-4h', 80, 'Bestseller', 'gastro-croisiere-diner.jpg'),
            $mk('Déjeuner-croisière 3 plats sur la Seine', 'Paris', '4.9', 178, 'Journée', 135, null, 'gastro-croisiere-dejeuner.jpg'),
            $mk('Dîner Immersif 4K dans la jungle - Jungle Palace', 'Paris', '4.5', 134, '1 jour', 50, 'Bientôt complet', 'gastro-jungle.jpg'),
            $mk('Atelier macarons aux Galeries Lafayette', 'Paris, France', '4.8', 312, '1.5h', 80, null, 'gastro-macarons.jpg'),
            $mk('Atelier cuisine provençale', "Provence-Alpes-Côte d'Azur", '4.8', 64, '2h30', 25, null, 'gastro-provencale.jpg'),
            $mk('Atelier chocolat à Choco-Story', "Provence-Alpes-Côte d'Azur", '5.0', 93, '3h', 180, null, 'gastro-chocolat.jpg'),
            $mk('Dégustations de vins et de fromages avec chef sommelier', 'Auvergne-Rhône-Alpes', '4.9', 37, '1h30', 25, null, 'gastro-vins.jpg'),
            $mk('Visite culinaire traditionnelle du quartier Latin', 'Lyon, Auvergne-Rhône-Alpes', '4.5', 68, '3h', 30, null, 'gastro-latin.jpg'),
            $mk('Cours de fabrication de croissants avec chef', "Provence-Alpes-Côte d'Azur", '4.8', 64, '1 jour', 25, null, 'gastro-croissants.jpg'),
            $mk('No Diet Club - Une sélection des meilleurs burgers de Paris !', 'Paris, Seine', '5.0', 93, '3h', 40, null, 'gastro-burgers.jpg'),
            $mk('Cours de pâtisserie avec une grand-mère française', 'Paris, Louvre', '4.9', 37, '1h30', 25, 'Nouvelle activité', 'gastro-patisserie.jpg'),
            $mk("Cours de fabrication d'éclairs et de choux", 'Lyon, Auvergne-Rhône-Alpes', '4.5', 68, '3h', 45, null, 'gastro-eclairs.jpg'),
        ];
    }

    /**
     * Écran 1 : la mosaïque « Toutes nos destinations » — 15 tuiles sur une
     * grille de 8 colonnes (small = 2 col, wide = 3 col, xwide = 4 col,
     * tall = 2 col × 2 rangées), placements repris de la maquette.
     *
     * @return list<array<string, mixed>>
     */
    public static function mosaic(): array
    {
        $t = static fn (string $name, int $count, string $image, string $span, int $col, int $row, string $align = 'left'): array => [
            'name' => $name,
            'count' => $count,
            'image' => 'images/destinations/'.$image,
            'span' => $span,
            'col' => $col,
            'row' => $row,
            'align' => $align,
        ];

        // Villes, volumes et emplacements repris case par case de la maquette
        // (Destinations.png). Seule exception : Nice, dont la maquette porte
        // « 00 Activités » (compteur non renseigne) — on garde une valeur.
        return [
            $t('Lille', 158, 'ville-lille.jpg', 'small', 1, 1),
            $t('Bordeaux', 350, 'ville-bordeaux.jpg', 'small', 3, 1),
            $t('Toulouse', 154, 'ville-toulouse.jpg', 'small', 5, 1),
            $t('Reims', 59, 'ville-reims.jpg', 'tall', 7, 1),
            $t('Nantes', 241, 'ville-nantes.jpg', 'wide', 1, 2, 'center'),
            $t('Marseille', 421, 'ville-marseille.jpg', 'wide', 4, 2, 'center'),
            $t('Grenoble', 125, 'ville-grenoble.jpg', 'tall', 1, 3),
            $t('Clermont-Ferrand', 194, 'ville-clermont.jpg', 'small', 3, 3),
            $t('Annecy', 59, 'ville-annecy.jpg', 'small', 5, 3),
            $t('Paris', 215, 'ville-paris.jpg', 'small', 7, 3),
            $t('Nice', 112, 'ville-nice.jpg', 'wide', 3, 4, 'center'),
            $t('Dijon', 154, 'ville-dijon.jpg', 'wide', 6, 4, 'center'),
            $t('Strasbourg', 252, 'ville-strasbourg.jpg', 'small', 1, 5),
            $t('La Rochelle', 156, 'ville-larochelle.jpg', 'small', 3, 5),
            $t('Saint-Malo', 426, 'ville-saintmalo.jpg', 'xwide', 5, 5, 'center'),
        ];
    }

    /**
     * Écran 1 : les 5 catégories populaires du carrousel.
     *
     * @return list<array<string, mixed>>
     */
    public static function popularCategories(): array
    {
        return [
            // Libelles, volumes et pictogramme repris de la maquette
            // (Destinations.png) : elle pose le meme glyphe sur les cinq.
            ['label' => 'Sports & Aventures', 'icon' => 'cat_canoe', 'count' => '10+ activités'],
            ['label' => 'Bien-être', 'icon' => 'cat_canoe', 'count' => '26+ activités'],
            ['label' => 'Cultures & Découvertes', 'icon' => 'cat_canoe', 'count' => '55+ activités'],
            ['label' => 'Romantiques', 'icon' => 'cat_canoe', 'count' => '10+ activités'],
            ['label' => 'Gastronomies', 'icon' => 'cat_canoe', 'count' => '34+ activités'],
        ];
    }

    /**
     * Écran 1 : les 3 « Idées du moment » de la bannière découverte.
     *
     * @return list<array<string, string>>
     */
    public static function ideas(): array
    {
        return [
            // Contenu repris de la maquette (Destinations.png, bandeau
            // « Partez a la decouverte du monde »).
            ['title' => 'Les Alpes françaises', 'subtitle' => 'Nature & Aventure', 'image' => 'images/destinations/dest-suisse-1.jpg'],
            ['title' => 'Road trip en Italie', 'subtitle' => 'Culture & Gastronomie', 'image' => 'images/destinations/dest-amalfi.jpg'],
            ['title' => 'Bali, Indonésie', 'subtitle' => 'Bien-être & Détente', 'image' => 'images/destinations/dest-thailande.jpg'],
        ];
    }

    /**
     * Écrans 2 à 7 : les avis « Ce que disent les voyageurs » (2 cartes).
     *
     * @return list<array<string, mixed>>
     */
    public static function travelerReviews(): array
    {
        return [
            [
                'stars' => 5,
                'title' => 'Efficient and Reliable',
                'text' => 'Réservation simple et rapide, prestataire au top et activité conforme à la description. On sent que la plateforme sélectionne bien ses partenaires — je recommande les yeux fermés.',
                'author' => 'John Mans',
                'meta' => 'France, Nantes',
                'date' => '10 Juillet 2026',
                'avatar' => 'images/home/avatar-2.png',
                'reportable' => true,
            ],
            [
                'stars' => 5,
                'title' => 'Des souvenirs pour toute la famille',
                'text' => "Nous avons réservé trois activités pour notre séjour à Annecy : tout était impeccable, des rappels par mail jusqu'à l'accueil sur place. Les enfants en parlent encore !",
                'author' => 'Amélie Robert',
                'meta' => 'France, Annecy',
                'date' => '2 Juillet 2026',
                'avatar' => 'images/home/avatar-3.png',
                'reportable' => true,
            ],
        ];
    }
}
