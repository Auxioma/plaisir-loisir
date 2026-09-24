<?php

declare(strict_types=1);

namespace App\Event;

/**
 * Données statiques restantes du flow navigation Événements (spec
 * « Partie 2 — Événements », 31 captures) : catégories éditoriales,
 * participants et membres décoratifs (aucune entité d'inscription/adhésion
 * n'existe encore), sélections et avatars de remplissage.
 *
 * Les événements, groupes, albums et calendrier viennent désormais des
 * entités réelles (Event, Group, GroupAlbum — voir EventsController et les
 * presenters du domaine) : leurs méthodes figées d'origine ont été retirées
 * le 17/09, une fois plus rien ne les appelant.
 */
final class StaticEvents
{
    /**
     * Carousel « Categories populaires » du landing (icônes rondes violettes).
     *
     * @return list<array{label: string, icon: string}>
     */
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
     * Les 12 participants de l'écran D (badges de rôle optionnels).
     *
     * Décoratif : aucune entité d'inscription à un événement n'existe encore
     * (voir EventsController::detail()) — le compte réel de participants,
     * lui, vient de Event::participantsCount.
     *
     * @return list<array<string, string|null>>
     */
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
     *
     * Décoratif : aucune entité d'adhésion à un groupe n'existe encore — le
     * compte réel de membres, lui, vient de Group::membersCount.
     *
     * @return list<array{name: string, avatar: string, hovered: bool}>
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
     * Section « Votre sélection D'événements » (cartes catégorie).
     *
     * @return list<array{title: string, count: string, image: string}>
     */
    public static function selections(): array
    {
        return [
            ['title' => 'Santé et bien-être', 'count' => '88 événements', 'image' => 'images/events/sel-spa.jpg'],
            ['title' => 'Culture & Découverte', 'count' => '254 événements', 'image' => 'images/home/act-musee.jpg'],
            ['title' => 'Sports & Aventures', 'count' => '254 événements', 'image' => 'images/events/sel-kayak.jpg'],
            ['title' => 'Atéliers & Créations', 'count' => '254 événements', 'image' => 'images/events/sel-cuisine.jpg'],
        ];
    }

    /**
     * Carrousel de villes de la sélection.
     *
     * @return list<string>
     */
    public static function cities(): array
    {
        return ['Paris', 'Bordeaux', 'Toulouse', 'Reims', 'Annecy', 'Nice', 'Marseille', 'Grenoble', 'Dijon'];
    }

    /**
     * Pile d'avatars des cartes (4 visibles + pile).
     *
     * @return list<string>
     */
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
