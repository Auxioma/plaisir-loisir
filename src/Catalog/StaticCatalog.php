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
                'lat' => 44.405,
                'lng' => 4.395,
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
                'lat' => 45.050,
                'lng' => 5.400,
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
                'lat' => 43.830,
                'lng' => 5.050,
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
                'lat' => 48.842,
                'lng' => 2.356,
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
                'lat' => 43.530,
                'lng' => 5.450,
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
                'lat' => 43.900,
                'lng' => 6.000,
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
                'lat' => 45.360,
                'lng' => 4.800,
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
                'lat' => 45.760,
                'lng' => 4.840,
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

    /**
     * Groupes d'activités affichés en « clusters » sur la vue carte
     * (pastilles violettes avec compteur, écran E).
     *
     * @return list<array<string, mixed>>
     */
    public static function mapClusters(): array
    {
        return [
            ['lat' => 43.55, 'lng' => 7.02, 'count' => 2],   // Côte d'Azur
            ['lat' => 45.90, 'lng' => 6.13, 'count' => 3],   // Annecy
            ['lat' => 44.84, 'lng' => -0.58, 'count' => 4],  // Bordeaux
        ];
    }

    /**
     * Fiche détaillée d'une activité (écran F). Seule la descente en
     * canoë est renseignée pour l'instant (l'activité vedette de la
     * maquette) ; les autres retomberont dessus le temps du câblage.
     *
     * @return array<string, mixed>
     */
    public static function detail(string $slug): array
    {
        return [
            'breadcrumb' => ['Accueil', 'Toutes les destinations', 'Paris, France', 'Sports & aventures'],
            'title' => 'Descente en Canoë',
            'rating' => '4.8',
            'reviewsCount' => 256,
            'organizer' => 'Thomas Martin',
            'gallery' => [
                'images/activities/gallery-1.jpg',
                'images/home/act-canoe.jpg',
                'images/activities/canoe-riviere.jpg',
                'images/activities/gallery-2.jpg',
                'images/activities/gallery-3.jpg',
            ],
            'place' => "Gorges de L'Ardèche",
            'keyFacts' => [
                ['label' => 'Durée', 'value' => '2h-3h'],
                ['label' => 'Maximum de personnes', 'value' => '18 personnes'],
                ['label' => "Moyenne d'âge", 'value' => '12 ans +'],
                ['label' => "Type d'activités", 'value' => 'Sport & Aventure'],
                ['label' => 'Avis clients', 'value' => '4.8 (15 avis)', 'star' => true],
            ],
            'price' => 29,
            'presentation' => [
                'subtitle' => "Descente intégrale des Gorges de l'Ardèche en canoë kayak",
                'text' => "Vivez une aventure inoubliable au cœur d'un des plus beaux canyons d'Europe. Accompagné de votre moniteur diplômé, pagayez au fil de l'eau entre falaises vertigineuses et plages sauvages, à votre rythme, en famille ou entre amis.",
                'bulletsTitle' => 'Cette descente sportive vous permet de :',
                'bullets' => [
                    "Passer sous l'arche naturelle du Pont d'Arc, emblème des Gorges",
                    "Traverser la Réserve Naturelle des Gorges de l'Ardèche",
                    'Admirer des paysages spectaculaires inaccessibles par la route',
                    'Profiter de pauses baignade dans une eau limpide',
                ],
            ],
            'included' => [
                'La location des bateaux et du matériel de navigation',
                'Location du petit matériel (pagaies, gilets, bidons étanches)',
                "L'initiation de départ avec un moniteur diplômé",
                'Le retour des personnes & du matériel en fin de parcours',
            ],
            'excluded' => [
                'Les repas et les boissons',
                "L'équipement personnel (chaussures fermées, maillot)",
                "Le transport jusqu'à l'embarcadère de départ",
                "L'assurance annulation personnelle",
            ],
            'cannotParticipate' => [
                'Enfants de moins de 7 ans',
                'Femmes enceintes',
                'Personnes ne sachant pas nager',
                'Parcours non adapté aux débutants',
            ],
            'toBring' => [
                "De l'eau (1,5 L par personne minimum) et un pique-nique",
                'Chaussures fermées, maillot de bain, serviette, crème solaire et lunettes attachées',
            ],
            'logistics' => [
                'map' => 'images/activities/map.jpg',
                'meeting' => [
                    ['label' => 'Lieu de départ', 'value' => "A 8h30 depuis l'embarcadère"],
                    ['label' => 'Gorges de l\'Ardèche', 'value' => "Vallon-Pont-d'Arc, France"],
                    ['label' => 'Arrivée à', 'value' => 'Embarcadère parking Alain Bateau'],
                ],
                'guarantees' => [
                    ['title' => 'Annulation : Flexible', 'text' => "Annulation gratuite jusqu'à 7 jours avant le départ."],
                    ['title' => 'Garantie méteo', 'text' => 'Report ou remboursement si la météo est défavorable.'],
                    ['title' => 'Paiement 100% sécurisé', 'text' => 'Réglez en toute confiance par carte ou Paypal.'],
                    ['title' => "Une équipe d'experts", 'text' => 'À votre service 7j/7 pour préparer votre sortie.'],
                ],
            ],
            'reviewsSummary' => ['score' => '4,5', 'outOf' => 5, 'total' => 8955],
            'modalTitle' => "Descente en Canoë de l'Ardèche de Gorges : 02 heures",
        ];
    }

    /**
     * Avis clients (section « Ce que disent nos clients » + modale
     * « Tous les avis »). Les 2 premiers alimentent la page de détail.
     *
     * @return list<array<string, mixed>>
     */
    public static function reviews(): array
    {
        return [
            [
                'stars' => 5,
                'title' => 'Efficient and Reliable',
                'text' => "Une descente magnifique, encadrée avec beaucoup de professionnalisme. Le passage sous le Pont d'Arc restera gravé dans nos mémoires. Matériel en parfait état, équipe souriante : nous reviendrons !",
                'author' => 'Camille Robert',
                'meta' => 'France, Nantes',
                'date' => '10 Juillet 2026',
                'avatar' => 'images/home/avatar-2.png',
                'reportable' => false,
            ],
            [
                'stars' => 4,
                'title' => 'Un moment magique en famille',
                'text' => "Parcours superbe et pauses baignade très appréciées des enfants. Prévoyez de bonnes chaussures fermées ! Seul petit bémol : un peu d'attente à l'embarcadère au retour.",
                'author' => 'Julien Moreau',
                'meta' => 'France, Lyon',
                'date' => '2 Juillet 2026',
                'avatar' => 'images/home/avatar-3.png',
                'reportable' => true,
            ],
            [
                'stars' => 5,
                'title' => 'À refaire sans hésiter',
                'text' => "L'initiation de départ met tout de suite en confiance. Les paysages sont à couper le souffle et l'eau est limpide. Une organisation au cordeau du début à la fin.",
                'author' => 'Sophie Lambert',
                'meta' => 'France, Bordeaux',
                'date' => '28 Juin 2026',
                'avatar' => 'images/home/avatar-4.png',
                'reportable' => true,
            ],
            [
                'stars' => 5,
                'title' => 'Paysages spectaculaires',
                'text' => 'La traversée de la Réserve Naturelle est un émerveillement permanent. Le moniteur connaît chaque recoin des gorges et partage plein d\'anecdotes.',
                'author' => 'Antoine Girard',
                'meta' => 'France, Toulouse',
                'date' => '21 Juin 2026',
                'avatar' => 'images/home/avatar-1.png',
                'reportable' => true,
            ],
            [
                'stars' => 4,
                'title' => 'Très bonne organisation',
                'text' => "Accueil ponctuel, consignes claires, matériel récent. La descente demande un peu d'énergie mais reste accessible. Pensez à la crème solaire !",
                'author' => 'Marie Dupont',
                'meta' => 'France, Paris',
                'date' => '15 Juin 2026',
                'avatar' => 'images/home/avatar-2.png',
                'reportable' => true,
            ],
            [
                'stars' => 5,
                'title' => 'Le plus beau souvenir de nos vacances',
                'text' => "Entre les falaises, les plages sauvages et la baignade, tout était parfait. Le retour en navette est très bien organisé. Merci à toute l'équipe !",
                'author' => 'Nicolas Perrin',
                'meta' => 'France, Marseille',
                'date' => '8 Juin 2026',
                'avatar' => 'images/home/avatar-3.png',
                'reportable' => true,
            ],
        ];
    }

    /**
     * Suggestions « Vous pourriez aussi aimer… » du détail (écran F).
     *
     * @return list<array<string, mixed>>
     */
    public static function suggestions(): array
    {
        $activities = self::activities();

        return [
            [
                'slug' => 'descente-en-canoe',
                'place' => "Gorges de l'Ardèche",
                'title' => 'Descente Canoë Kayak à Salavas',
                'rating' => '4.8',
                'reviews' => 256,
                'duration' => '2h-3h',
                'price' => 25,
                'badge' => 'Bestseller',
                'image' => 'images/activities/kayak-lagon.jpg',
            ],
            [
                'slug' => null,
                'place' => 'Pays de Galles, Royaume-Uni',
                'title' => "Excursion en canoë à l'aqueduc de Pontcysyllte",
                'rating' => '4.8',
                'reviews' => 178,
                'duration' => 'Journée',
                'price' => 45,
                'badge' => null,
                'image' => 'images/activities/canoe-riviere.jpg',
            ],
            $activities['visite-guidee-de-labyrinthe'],
            $activities['visite-du-musee'],
        ];
    }
}
