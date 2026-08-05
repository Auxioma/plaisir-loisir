<?php

declare(strict_types=1);

namespace App\User;

/**
 * Données statiques de l'espace compte « Paramètre du profil » (spec profil :
 * sidebar, favoris, listes, notifications). Même approche que StaticCatalog :
 * contenu figé de la maquette en attendant les vraies entités
 * Favorite/Notification (déjà identifiées par la maquette beta).
 */
final class StaticAccount
{
    /**
     * Profil affiché dans la sidebar (et prénom de l'écran Déconnexion).
     *
     * @return array{name: string, firstName: string, email: string, avatar: string, memberSince: string, unread: int}
     */
    public static function user(): array
    {
        return [
            'name' => 'Thomas Martin',
            'firstName' => 'Thomas',
            'email' => 'Tmartin@email.com',
            'avatar' => 'images/account/avatar-thomas.jpg',
            'memberSince' => 'Mai 2026',
            // Badge « non lues » partagé sidebar/header (spec, point 8).
            'unread' => 3,
        ];
    }

    /**
     * Menu de la sidebar. Les entrées sans maquette (route null) restent
     * inertes — pages à concevoir (spec, « entrées non maquettées »).
     *
     * @return list<array{icon: string, title: string, subtitle: string, route: string|null, badge: bool}>
     */
    public static function menu(): array
    {
        return [
            ['icon' => 'grid', 'title' => 'Tableau de bord', 'subtitle' => 'Aperçu de votre activité', 'route' => null, 'badge' => false],
            ['icon' => 'camera', 'title' => 'Mes albums photos', 'subtitle' => 'Gérez vos albums et photos', 'route' => null, 'badge' => false],
            ['icon' => 'badge_check', 'title' => 'Mes activités créées', 'subtitle' => 'Gérez vos activités sur Event', 'route' => null, 'badge' => false],
            ['icon' => 'receipt', 'title' => 'Mes réservations', 'subtitle' => 'Suivi de vos réservations', 'route' => null, 'badge' => false],
            ['icon' => 'heart', 'title' => 'Mes favoris', 'subtitle' => 'Vos activités favorites', 'route' => 'app_account_favorites', 'badge' => false],
            ['icon' => 'bell', 'title' => 'Notifications', 'subtitle' => 'Vos notifications et alertes', 'route' => 'app_account_notifications', 'badge' => true],
            ['icon' => 'hand_heart', 'title' => 'Parrainage', 'subtitle' => 'Invitez vos amis', 'route' => 'app_account_referral', 'badge' => false],
            ['icon' => 'gear', 'title' => 'Paramètres du compte', 'subtitle' => 'Supprimer ou désactiver', 'route' => null, 'badge' => false],
            ['icon' => 'logout', 'title' => 'Déconnexion', 'subtitle' => 'Fermer votre session', 'route' => 'app_account_logout_confirm', 'badge' => false],
        ];
    }

    /**
     * Grille des favoris (onglets Activités/Destinations/Prestataires) :
     * mêmes 6 activités que le catalogue, valeurs de la maquette. Le titre
     * « Titre » est un placeholder de la maquette, reproduit tel quel.
     * Coquilles corrigées : « Labyrinthe en Province » → Provence,
     * « Procince-Alpes-Côte d'Azur » → Provence.
     *
     * @return list<array<string, string|int|null>>
     */
    public static function favorites(): array
    {
        return [
            ['place' => "Gorges de L'ardèche", 'title' => 'Titre', 'rating' => '4.8', 'reviews' => 256, 'duration' => '2h-3h', 'price' => 25, 'badge' => 'Bestseller', 'image' => 'images/account/fav-kayak.jpg'],
            ['place' => 'Massif du Vercors', 'title' => 'Titre', 'rating' => '4.9', 'reviews' => 178, 'duration' => 'Journée', 'price' => 45, 'badge' => null, 'image' => 'images/account/fav-vtt.jpg'],
            ['place' => 'Labyrinthe en Provence', 'title' => 'Titre', 'rating' => '4.7', 'reviews' => 134, 'duration' => '1h30', 'price' => 12, 'badge' => null, 'image' => 'images/home/act-labyrinthe.jpg'],
            ['place' => "Muséum d'Histoire Naturelle", 'title' => 'Titre', 'rating' => '4.6', 'reviews' => 312, 'duration' => '2h', 'price' => 16, 'badge' => null, 'image' => 'images/home/act-musee.jpg'],
            ['place' => "Provence-Alpes-Côte d'Azur", 'title' => 'Titre', 'rating' => '4.8', 'reviews' => 64, 'duration' => '2h30', 'price' => 25, 'badge' => null, 'image' => 'images/account/fav-cuisine.jpg'],
            ['place' => "Provence-Alpes-Côte d'Azur", 'title' => 'Titre', 'rating' => '5.0', 'reviews' => 93, 'duration' => '3h', 'price' => 180, 'badge' => null, 'image' => 'images/activities/montgolfiere.jpg'],
        ];
    }

    /**
     * Onglet Prestataires : même grille mais la 1re carte est un
     * `<ImagePlaceholder>` (chip « Badge », icône image manquante) avec un
     * vrai titre « Descente en Canoë » ; cœurs en contour (maquette).
     *
     * @return list<array<string, string|int|bool|null>>
     */
    public static function providers(): array
    {
        $cards = self::favorites();
        $cards[0] = [
            'place' => "Gorges de L'ardèche",
            'title' => 'Descente en Canoë',
            'rating' => '4.8',
            'reviews' => 256,
            'duration' => '2h-3h',
            'price' => 25,
            'badge' => null,
            'image' => null,
            'placeholder' => true,
        ];

        return $cards;
    }

    /**
     * Liste de favoris « Alsace - 2026 » (écran 2) : 6 cartes croppées de la
     * maquette, localisées Alsace / Alsace-Colmar.
     *
     * @return list<array<string, string|int|null>>
     */
    public static function alsaceList(): array
    {
        return [
            ['place' => 'Alsace', 'title' => 'Titre', 'rating' => '4.9', 'reviews' => 178, 'duration' => 'Journée', 'price' => 45, 'badge' => null, 'image' => 'images/account/alsace-chocolat.jpg'],
            ['place' => 'Alsace', 'title' => 'Titre', 'rating' => '4.8', 'reviews' => 256, 'duration' => '2h-3h', 'price' => 25, 'badge' => 'Bestseller', 'image' => 'images/account/alsace-chateau.jpg'],
            ['place' => 'Alsace', 'title' => 'Titre', 'rating' => '4.7', 'reviews' => 134, 'duration' => '1h30', 'price' => 12, 'badge' => null, 'image' => 'images/account/alsace-brunch.jpg'],
            ['place' => 'Alsace-Colmar', 'title' => 'Titre', 'rating' => '4.6', 'reviews' => 312, 'duration' => '2h', 'price' => 16, 'badge' => null, 'image' => 'images/account/alsace-galerie.jpg'],
            ['place' => 'Alsace-Colmar', 'title' => 'Titre', 'rating' => '4.8', 'reviews' => 64, 'duration' => '2h30', 'price' => 25, 'badge' => null, 'image' => 'images/account/alsace-colmar.jpg'],
            ['place' => 'Alsace', 'title' => 'Titre', 'rating' => '5.0', 'reviews' => 93, 'duration' => '3h', 'price' => 180, 'badge' => null, 'image' => 'images/account/alsace-helico.jpg'],
        ];
    }

    /**
     * Notifications groupées par section temporelle. `tone` pilote la
     * couleur de l'icône ronde ; `muted` reproduit le fond gris de la
     * maquette (items 3 et 6). Coquilles corrigées : « dasn » → dans,
     * « alaissé » → a laissé, « électique » → électrique, « Ardècge » →
     * Ardèche.
     *
     * @return list<array{section: string, items: list<array<string, string|bool|null>>}>
     */
    public static function notifications(): array
    {
        return [
            [
                'section' => "Aujourd'hui",
                'items' => [
                    ['icon' => 'heart', 'tone' => 'violet', 'title' => 'Votre activité a reçu un nouveau favori', 'detail' => "“Descente en canoe dans les Gorges de l'Ardèche”", 'time' => 'Il y a 10 minutes', 'thumb' => 'images/account/fav-kayak.jpg', 'muted' => false],
                    ['icon' => 'calendar_check', 'tone' => 'violet', 'title' => 'Nouvelle réservation confirmée', 'detail' => 'Atelier cuisine provençale', 'meta' => '24 Mai 2026 à 10h00', 'time' => 'Il y a 1 heures', 'thumb' => 'images/account/fav-cuisine.jpg', 'muted' => false],
                    ['icon' => 'heart', 'tone' => 'green', 'title' => 'Nouveau message de Sophie', 'detail' => "Bonjour Thomas, j'aimerais en savoir plus sur votre activités…", 'time' => 'Il y a 3 heures', 'thumb' => null, 'muted' => true],
                ],
            ],
            [
                'section' => 'Hier',
                'items' => [
                    ['icon' => 'heart', 'tone' => 'blue', 'title' => 'Rappel: Votre réservation arrive bientôt', 'detail' => 'Séance de yoga en pleine nature', 'meta' => '24 Mai 2026 à 10h00', 'time' => 'Hier à 18:30', 'thumb' => 'images/account/notif-yoga.jpg', 'muted' => false],
                    ['icon' => 'calendar_check', 'tone' => 'green', 'title' => 'Votre activité a été publiée', 'detail' => '“Vol en montgolfière en provence ” est maintenant en ligne !', 'time' => 'Hier à 11:45', 'thumb' => 'images/activities/montgolfiere.jpg', 'muted' => false],
                    ['icon' => 'heart', 'tone' => 'yellow', 'title' => 'Nouveau message de Sophie', 'detail' => 'Jean D. a laissé un commentaire sur “Location VTT électrique”', 'time' => 'Hier à 09:15', 'thumb' => null, 'muted' => true],
                ],
            ],
        ];
    }
}
