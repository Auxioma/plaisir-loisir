<?php

declare(strict_types=1);

namespace App\Event;

/**
 * Données statiques du flow navigation Événements (spec « Partie 2 —
 * Événements », 31 captures) : événements communautaires, groupes,
 * participants, membres, albums photo et calendrier mensuel.
 * Même approche que StaticCatalog/StaticGifts : contenu figé de la
 * maquette en attendant les vraies entités.
 */
final class StaticEvents
{
    /**
     * Les 12 événements récurrents de la maquette (grille 4×3 du listing,
     * réutilisés sur le landing, le calendrier et les événements privés).
     * Couleur du badge mappée à la catégorie (spec §E1).
     */
    public static function events(): array
    {
        $base = [
            [
                'title' => 'Compétitions  Canoë / Kayak',
                'category' => 'Sports',
                'color' => 'blue',
                'image' => 'images/events/ev-raft.jpg',
                'location' => 'Autrans, 38880',
                'hours' => '9h00 - 16h00',
                'participants' => '12',
                'date' => '15 Mai 2026',
            ],
            [
                'title' => 'Match de foot amical',
                'category' => 'Sports',
                'color' => 'blue',
                'image' => 'images/events/ev-foot.jpg',
                'location' => 'Toulouse, 31000',
                'hours' => '10h00 - 12h00',
                'participants' => '8',
                'date' => '15 Mai 2026',
            ],
            [
                'title' => 'Barbecue entre amis',
                'category' => 'Repas',
                'color' => 'orange',
                'image' => 'images/events/ev-bbq.jpg',
                'location' => 'Lyon, 69000',
                'hours' => '12h00 - 18h00',
                'participants' => '12',
                'date' => '18 Mai 2026',
            ],
            [
                'title' => 'Séance de yoga en plein air',
                'category' => 'Bien-être',
                'color' => 'violet',
                'image' => 'images/events/ev-yoga.jpg',
                'location' => 'Nantes, 44000',
                'hours' => '10h00 - 11h30',
                'participants' => '12',
                'date' => '02 Juin 2026',
            ],
        ];

        $rando = [
            'title' => 'Randonnée dans le Vercors',
            'category' => 'Randonnée',
            'color' => 'green',
            'image' => 'images/events/ev-rando.jpg',
            'location' => 'Autrans, 38880',
            'hours' => '9h00 - 16h00',
            'participants' => '12',
            'date' => '15 Mai 2026',
        ];

        // Ligne 2 : Randonnée + répétitions ; ligne 3 : mêmes visuels avec
        // les badges Culture / En famille / Jeu / Loisirs de la maquette.
        return array_merge($base, [
            $rando,
            $base[1],
            $base[2],
            $base[3],
            array_merge($base[2], ['category' => 'Culture', 'color' => 'orange']),
            array_merge($rando, ['category' => 'En famille', 'color' => 'green']),
            array_merge($base[1], ['category' => 'Jeu', 'color' => 'navy']),
            array_merge($base[3], ['category' => 'Loisirs', 'color' => 'violet']),
        ]);
    }

    /**
     * Variante du listing « Tous les événements » / « Événements privés » :
     * mêmes 8 premières cartes, mais la 3ᵉ ligne de la maquette y échange
     * badges et visuels (Barbecue « En famille » photo famille, Randonnée
     * « Culture » photo catacombes, Match « Jeu », Yoga « Loisirs »).
     */
    public static function eventsListing(): array
    {
        $events = self::events();

        return array_merge(array_slice($events, 0, 8), [
            [
                'title' => 'Barbecue entre amis',
                'category' => 'En famille',
                'color' => 'green',
                'image' => 'images/events/ev-famille.jpg',
                'location' => 'Autrans, 38880',
                'hours' => '9h00 - 16h00',
                'participants' => '12',
                'date' => '15 Mai 2026',
            ],
            [
                'title' => 'Randonnée dans le Vercors',
                'category' => 'Culture',
                'color' => 'orange',
                'image' => 'images/events/ev-catacombes.jpg',
                'location' => 'Lyon, 69000',
                'hours' => '12h00 - 18h00',
                'participants' => '12',
                'date' => '18 Mai 2026',
            ],
            array_merge($events[1], ['category' => 'Jeu', 'color' => 'navy']),
            array_merge($events[3], ['category' => 'Loisirs', 'color' => 'violet']),
        ]);
    }

    /**
     * Grille filtrée affichée quand la sidebar est ouverte (capture 9) :
     * 9 cartes, 3 colonnes, ordre propre à la maquette.
     */
    public static function eventsFiltered(): array
    {
        $listing = self::eventsListing();

        return [
            $listing[4],  // Randonnée dans le Vercors (Randonnée)
            $listing[1],  // Match de foot amical (Sports)
            $listing[2],  // Barbecue entre amis (Repas)
            $listing[3],  // Séance de yoga (Bien-être)
            $listing[8],  // Barbecue entre amis (En famille, photo famille)
            $listing[9],  // Randonnée dans le Vercors (Culture, catacombes)
            $listing[10], // Match de foot amical (Jeu)
            $listing[11], // Séance de yoga (Loisirs)
            $listing[2],  // Barbecue entre amis (Repas)
        ];
    }

    /** Carousel « Categories populaires » du landing (icônes rondes violettes). */
    public static function categories(): array
    {
        return [
            ['label' => 'Canoë / Kayak', 'icon' => 'cat_canoe'],
            ['label' => 'VTT / Vélo', 'icon' => 'cat_bike'],
            ['label' => 'Randonnée', 'icon' => 'cat_hiking'],
            ['label' => 'Sports & Sensations', 'icon' => 'cat_sports'],
            ['label' => 'Visites culturelles', 'icon' => 'cat_culture'],
            ['label' => 'Atéliers & Créations', 'icon' => 'cat_crafts'],
            ['label' => 'Bien-être', 'icon' => 'cat_wellness'],
            ['label' => 'En famille', 'icon' => 'cat_family'],
        ];
    }

    /**
     * Les 16 groupes du listing E (grille 4×4). Coquilles maquette
     * corrigées : « nouritures » → nourritures, « FRance » → France,
     * « societé » → société.
     */
    public static function groups(): array
    {
        $lorem = 'Description du groupe simply dummy text of the printing and typesetting industry. Lorem Ipsum';

        return [
            ['name' => 'Cours collectifs de fitness à Lyon', 'image' => 'images/events/grp-fitness.jpg', 'description' => 'Vous voulez bouger, vous amuser et retrouver la forme ?Nous animons ce groupe pour promouvoir des séances…', 'location' => 'Lyon, 38880', 'members' => '5246', 'badge' => null],
            ['name' => 'Retour à la nature', 'image' => 'images/events/grp-nature.jpg', 'description' => $lorem, 'location' => 'Toulouse, 31000', 'members' => '4562', 'badge' => null],
            ['name' => 'Grenoble aventure club', 'image' => 'images/events/grp-grenoble.jpg', 'description' => $lorem, 'location' => 'Grenoble , France', 'members' => '124', 'badge' => null],
            ['name' => 'Soul & Flow yoga', 'image' => 'images/events/ev-yoga-clean.jpg', 'description' => $lorem, 'location' => 'Nantes, 44000', 'members' => '356', 'badge' => null],
            ['name' => 'Paris Art Community', 'image' => 'images/events/grp-art.jpg', 'description' => $lorem, 'location' => 'Autrans, 38880', 'members' => '6842', 'badge' => null],
            ['name' => 'Club de lecture', 'image' => 'images/events/grp-lecture.jpg', 'description' => $lorem, 'location' => 'Dijon, 69000', 'members' => '254', 'badge' => null],
            ['name' => 'Culture & Conversation Café', 'image' => 'images/events/grp-culturecafe.jpg', 'description' => $lorem, 'location' => 'Toulouse, 31000', 'members' => '3541', 'badge' => null],
            ['name' => 'Soul & Flow yoga', 'image' => 'images/events/grp-spa.jpg', 'description' => $lorem, 'location' => 'Nantes, 44000', 'members' => '356', 'badge' => null],
            ['name' => 'Les amoureux de nourritures', 'image' => 'images/events/grp-nourriture.jpg', 'description' => $lorem, 'location' => 'Autrans, 38880', 'members' => '254', 'badge' => null],
            ['name' => 'Meet in Paris', 'image' => 'images/events/grp-meetparis.jpg', 'description' => "Sorties & Soirées uniques à Paris 🌟\nPour les 24-40 ans\nRestez connectés", 'location' => 'Lyon, 69000', 'members' => '12', 'badge' => null],
            ['name' => 'Speed dating - Love et amitié', 'image' => 'images/events/grp-speeddating.jpg', 'description' => $lorem, 'location' => 'Nantes, 44000', 'members' => '1095', 'badge' => 'Nouveau'],
            ['name' => 'Randonnée dans le Vercors', 'image' => 'images/events/ev-catacombes.jpg', 'description' => $lorem, 'location' => 'Lyon, 69000', 'members' => '12', 'badge' => null],
            ['name' => 'Sortie 20 - 45 ans Paris', 'image' => 'images/events/grp-afterwork.jpg', 'description' => "Qui est pour un groupe WhatsApp de 6600 membres pour faire des sorties AMICALES pour les habitants de l'IDF …", 'location' => 'Paris', 'members' => '224', 'badge' => null],
            ['name' => "Jeu de société - C'est parti !", 'image' => 'images/events/grp-jeu.jpg', 'description' => 'Rejoignez le nouveau groupe  pour des soirées de jeu conviviales et détendues ! Tout le monde est le bienvenu: débu…', 'location' => 'Paris', 'members' => '224', 'badge' => null],
            ['name' => 'Afri-House in Paris', 'image' => 'images/events/grp-afrihouse.jpg', 'description' => "Bienvenue sur le groupe « Afro-house à Paris » ! Ce groupe s'adresse à tous ceux qui aiment la musique afro-hou…", 'location' => 'Lyon, 69000', 'members' => '12', 'badge' => 'Nouveau'],
            ['name' => 'Footballers de Nantes', 'image' => 'images/events/grp-footstreet.jpg', 'description' => 'Bienvenue chez FootballersNantes, une communauté ouverte à tous ceux qui aiment jouer au football dans une am…', 'location' => 'Nantes, 44000', 'members' => '154', 'badge' => null],
        ];
    }

    /**
     * Cartes « Vous aimerez peut-être aussi » / « événements similaires à
     * proximité » (cartes de type groupe, incohérence assumée par la
     * maquette — point à trancher n°1 documenté dans les templates).
     */
    public static function similar(): array
    {
        $lorem = 'Description du groupe simply dummy text of the printing and typesetting industry. Lorem Ipsum';

        return [
            ['name' => 'Barbecue entre amis', 'image' => 'images/events/ev-famille.jpg', 'description' => $lorem, 'location' => 'Autrans, 38880', 'members' => '12', 'badge' => null],
            ['name' => 'Randonnée dans le Vercors', 'image' => 'images/events/ev-catacombes.jpg', 'description' => $lorem, 'location' => 'Lyon, 69000', 'members' => '12', 'badge' => null],
            ['name' => 'Match de foot amical', 'image' => 'images/events/ev-foot-clean.jpg', 'description' => $lorem, 'location' => 'Toulouse, 31000', 'members' => '8', 'badge' => null],
            // La maquette affiche « membres » sans compteur sur la 4ᵉ carte.
            ['name' => 'Séance de yoga en plein air', 'image' => 'images/events/ev-yoga-clean.jpg', 'description' => $lorem, 'location' => 'Nantes, 44000', 'members' => '', 'badge' => null],
        ];
    }

    /** Les 12 participants de l'écran D (badges de rôle optionnels). */
    public static function participants(): array
    {
        return [
            ['name' => 'Martin Thomas', 'avatar' => 'images/events/avatar-thomas.jpg', 'role' => "Organisateur de l'événement", 'icon' => 'crown'],
            ['name' => 'Boris Dubois', 'avatar' => 'images/events/avatar-lucas.jpg', 'role' => 'Assistant organisateur', 'icon' => 'crown'],
            ['name' => 'Louise Alba', 'avatar' => 'images/events/avatar-marie.jpg', 'role' => 'Premier événement', 'icon' => 'party'],
            ['name' => 'Charlotte', 'avatar' => 'images/events/avatar-chloe.jpg', 'role' => 'Premier événement', 'icon' => 'party'],
            ['name' => 'Vincent', 'avatar' => 'images/events/avatar-sophie.jpg', 'role' => 'Premier événement', 'icon' => 'party'],
            ['name' => 'Gilbert', 'avatar' => 'images/events/avatar-lucas.jpg', 'role' => 'Premier événement', 'icon' => 'party'],
            ['name' => 'François', 'avatar' => 'images/events/avatar-sophie.jpg', 'role' => null, 'icon' => null],
            ['name' => 'Charlote', 'avatar' => 'images/events/avatar-thomas.jpg', 'role' => null, 'icon' => null],
            ['name' => 'Jade', 'avatar' => 'images/events/avatar-marie.jpg', 'role' => 'Premier événement', 'icon' => 'party'],
            ['name' => 'Jayden', 'avatar' => 'images/events/avatar-lucas.jpg', 'role' => null, 'icon' => null],
            ['name' => 'Alice', 'avatar' => 'images/events/avatar-chloe.jpg', 'role' => null, 'icon' => null],
            ['name' => 'Marc', 'avatar' => 'images/events/avatar-thomas.jpg', 'role' => null, 'icon' => null],
        ];
    }

    /**
     * Onglet Membres du groupe : 7 lignes « Richard » (maquette). Coquilles
     * corrigées : « Dernièrer visiste » → Dernière visite. Les lignes 2 et
     * 6 sont à l'état survolé sur la capture (icône message visible).
     */
    public static function members(): array
    {
        $avatars = [
            'images/events/avatar-lucas.jpg',
            'images/events/avatar-marie.jpg',
            'images/events/avatar-chloe.jpg',
            'images/events/avatar-thomas.jpg',
            'images/events/avatar-sophie.jpg',
            'images/events/avatar-lucas.jpg',
            'images/events/avatar-marie.jpg',
            'images/events/avatar-thomas.jpg',
        ];

        $rows = [];
        foreach ($avatars as $i => $avatar) {
            $rows[] = [
                'name' => 'Richard',
                'avatar' => $avatar,
                'hovered' => in_array($i, [0, 2, 6], true),
            ];
        }

        return $rows;
    }

    /**
     * Grille d'albums de l'onglet Photos (12 cartes). Coquilles corrigées :
     * Disneymland → Disneyland, bièrre → bière, Atélier → Atelier,
     * Auvergnbe → Auvergne, Championat → Championnat.
     */
    public static function albums(): array
    {
        $meta = '05 photos';
        $updated = 'Mis à jour le 28 Juill. 2026';

        return array_map(
            static fn (array $a): array => $a + ['photos' => $meta, 'updated' => $updated],
            [
                ['title' => 'Weekend spa  & bien-être', 'location' => 'Paris', 'image' => 'images/events/alb-spa.jpg'],
                ['title' => 'visite de musée de Louve', 'location' => 'Bordeaux', 'image' => 'images/events/alb-louvre.jpg'],
                ['title' => 'Spectacles à Disneyland', 'location' => 'Paris', 'image' => 'images/events/alb-disney.jpg'],
                ['title' => 'À la Découverte de nos Terroirs', 'location' => 'Nice', 'image' => 'images/events/alb-vins.jpg'],
                ['title' => 'Initiation au brassage de la bière', 'location' => 'Dijon', 'image' => 'images/events/alb-biere.jpg'],
                ['title' => 'Foodies + New Friends', 'location' => 'Lyon', 'image' => 'images/events/grp-nourriture.jpg'],
                ['title' => 'Championnat de Canoë-Kayak', 'location' => 'Lille', 'image' => 'images/events/alb-canoerouge.jpg'],
                ['title' => 'Visite Du Vulcania', 'location' => "Pays des volcans d'Auvergne", 'image' => 'images/events/alb-vulcania.jpg'],
                ['title' => 'Atelier pour enfant', 'location' => 'Nantes', 'image' => 'images/events/alb-louvre.jpg'],
                ['title' => 'Soirée karaoké', 'location' => 'Seine', 'image' => 'images/events/alb-karaoke.jpg'],
                ['title' => 'Excursion en bateau', 'location' => "Côte d'Azur", 'image' => 'images/events/alb-seine.jpg'],
                ['title' => 'Week-end création parfum', 'location' => "Pays des volcans d'Auvergne", 'image' => 'images/events/alb-parfum.jpg'],
            ]
        );
    }

    /**
     * Calendrier mensuel « Juillet 2026 » reproduit tel quel de la maquette
     * (numérotation ET pastilles) : jour courant 30 en violet, pastilles
     * colorées « Nom événement… / Heure ».
     */
    public static function calendar(): array
    {
        // Chaque cellule : [numéro, hors-mois ?, courant ?, couleur pastille|null]
        return [
            [[28, true, false, null], [29, true, false, 'violet'], [30, true, true, null], [1, false, false, null], [2, false, false, null], [3, false, false, 'red'], [4, false, false, null]],
            [[5, false, false, 'blue'], [6, false, false, null], [7, false, false, 'orange'], [8, false, false, null], [9, false, false, null], [10, false, false, null], [11, false, false, null]],
            [[12, false, false, null], [13, false, false, null], [14, false, false, null], [15, false, false, null], [16, false, false, null], [17, false, false, null], [18, false, false, 'blue']],
            [[20, false, false, null], [21, false, false, 'blue'], [22, false, false, null], [23, false, false, 'blue'], [24, false, false, null], [25, false, false, null], [26, false, false, null]],
            [[27, false, false, 'orange'], [28, false, false, null], [29, false, false, null], [30, false, false, null], [31, false, false, 'blue'], [1, true, false, null], [2, true, false, null]],
        ];
    }

    /** Cartes de l'onglet Événements du groupe (grille 4×3, type groupe). */
    public static function groupEvents(): array
    {
        $lorem = 'Description du groupe simply dummy text of the printing and typesetting industry. Lorem Ipsum';
        $randoDesc = 'Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum';

        $row = [
            ['name' => 'Randonnée dans le Vercors', 'image' => 'images/events/ev-rando-clean.jpg', 'description' => $randoDesc, 'location' => 'Autrans, 38880', 'members' => '12', 'badge' => null],
            ['name' => 'Match de foot amical', 'image' => 'images/events/ev-foot-clean.jpg', 'description' => $lorem, 'location' => 'Toulouse, 31000', 'members' => '8', 'badge' => null],
            ['name' => 'Barbecue entre amis', 'image' => 'images/events/ev-bbq-clean.jpg', 'description' => $lorem, 'location' => 'Lyon, 69000', 'members' => '12', 'badge' => null],
            ['name' => 'Séance de yoga en plein air', 'image' => 'images/events/ev-yoga-clean.jpg', 'description' => $lorem, 'location' => 'Nantes, 44000', 'members' => '12', 'badge' => null],
        ];

        return array_merge($row, $row, $row);
    }

    /** Section « Votre sélection D'événements » (cartes catégorie). */
    public static function selections(): array
    {
        return [
            ['title' => 'Santé et bien-être', 'count' => '88 événements', 'image' => 'images/events/sel-spa.jpg'],
            ['title' => 'Culture & Découverte', 'count' => '254 événements', 'image' => 'images/home/act-musee.jpg'],
            ['title' => 'Sports & Aventures', 'count' => '254 événements', 'image' => 'images/events/sel-kayak.jpg'],
            ['title' => 'Atéliers & Créations', 'count' => '254 événements', 'image' => 'images/events/sel-cuisine.jpg'],
        ];
    }

    /** Carrousel de villes de la sélection. */
    public static function cities(): array
    {
        return ['Paris', 'Bordeaux', 'Toulouse', 'Reims', 'Annecy', 'Nice', 'Marseille', 'Grenoble', 'Dijon'];
    }

    /** Pile d'avatars des cartes (4 visibles + pile). */
    public static function avatars(): array
    {
        return [
            'images/events/avatar-lucas.jpg',
            'images/events/avatar-marie.jpg',
            'images/events/avatar-chloe.jpg',
            'images/events/avatar-thomas.jpg',
            'images/events/avatar-sophie.jpg',
        ];
    }
}
