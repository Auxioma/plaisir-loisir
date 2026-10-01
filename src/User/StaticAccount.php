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
     * @return array{name: string, firstName: string, email: string, avatar: string, memberSince: string, unreadMessages: int, unreadNotifications: int}
     */
    public static function user(): array
    {
        return [
            'name' => 'Thomas Martin',
            'firstName' => 'Thomas',
            'email' => 'Tmartin@email.com',
            // Silhouette générique, pas la photo de la maquette (signalé le
            // 18/09) : c'est un repli affiché à N'IMPORTE QUEL compte tant
            // qu'il n'a pas déposé sa propre photo, pas une photo de démo à
            // remplacer plus tard — la personne choisit elle-même dans
            // /compte/parametres (AvatarStorageService).
            'avatar' => 'images/account/avatar-default.svg',
            'memberSince' => 'Mai 2026',
            // Badge « non lues » partagé sidebar/header (spec, point 8).
            // Séparé en deux compteurs (14/09, Lot G) : les deux sont réels
            // depuis le Lot K (MessageRepository puis NotificationRepository),
            // seul l'avatar ci-dessus reste celui du repli par défaut.
            'unreadMessages' => 3,
            'unreadNotifications' => 3,
        ];
    }

    /**
     * Menu de la sidebar client — maquettes docs/maquettes/profil_particulier
     * (30/09) : un premier groupe sans titre, puis le groupe « COMPTE ».
     *
     * `section` ouvre un nouveau groupe titré avant l'entrée qui le porte.
     * `badge` vaut `false` ou le nom du compteur (`'messages'`,
     * `'notifications'`, cf. AccountIdentityPresenter).
     *
     * « Messages » et « Mes demandes » n'apparaissent dans aucune maquette
     * mais portent des fonctions réelles (messagerie, devis) : ils sont
     * regroupés dans « ÉCHANGES » plutôt que rendus inaccessibles. Les
     * activités privées rejointes sont suivies dans « Mes réservations », les
     * organisées dans « Mes activités créées ».
     *
     * @return list<array{icon: string, title: string, subtitle: string, route: string|null, badge: string|false, section?: string}>
     */
    public static function menu(): array
    {
        return [
            ['icon' => 'grid', 'title' => 'Tableau de bord', 'subtitle' => 'Aperçu de votre activité', 'route' => 'app_account_dashboard', 'badge' => false],
            ['icon' => 'camera', 'title' => 'Mes albums photos', 'subtitle' => 'Gérez vos albums et photos', 'route' => 'app_account_albums', 'badge' => false],
            ['icon' => 'edit', 'title' => 'Mes activités créées', 'subtitle' => 'Gérez vos activités sur Event', 'route' => 'app_account_private_activities', 'badge' => false],
            ['icon' => 'calendar', 'title' => 'Mes réservations', 'subtitle' => 'Suivi de vos réservations', 'route' => 'app_account_history', 'badge' => false],
            ['icon' => 'person', 'title' => 'Informations personnelles', 'subtitle' => 'Gérez vos informations', 'route' => 'app_account_profile', 'badge' => false, 'section' => 'Compte'],
            ['icon' => 'wallet', 'title' => 'Paiements et abonnements', 'subtitle' => 'Gérez vos paiements', 'route' => 'app_account_payments', 'badge' => false],
            ['icon' => 'bell', 'title' => 'Notifications', 'subtitle' => 'Paramétrez vos notifications', 'route' => 'app_account_notifications', 'badge' => 'notifications'],
            ['icon' => 'heart', 'title' => 'Favoris', 'subtitle' => 'Vos activités favorites', 'route' => 'app_account_favorites', 'badge' => false],
            ['icon' => 'users', 'title' => 'Parrainage', 'subtitle' => 'Invitez vos amis', 'route' => 'app_account_referral', 'badge' => false],
            ['icon' => 'gear', 'title' => 'Paramètres du compte', 'subtitle' => 'Supprimer ou désactiver', 'route' => 'app_account_settings', 'badge' => false],
            ['icon' => 'mail', 'title' => 'Messages', 'subtitle' => 'Conversations avec les professionnels', 'route' => 'app_account_messages', 'badge' => 'messages', 'section' => 'Échanges'],
            ['icon' => 'receipt', 'title' => 'Mes demandes', 'subtitle' => 'Devis reçus des professionnels', 'route' => 'app_account_requests', 'badge' => false],
            ['icon' => 'logout', 'title' => 'Déconnexion', 'subtitle' => 'Se déconnecter', 'route' => 'app_account_logout_confirm', 'badge' => false, 'section' => ''],
        ];
    }

    /**
     * Menu de la sidebar pour un compte PRESTATAIRE (§8.3 du CDC), distinct
     * de menu() ci-dessus.
     *
     * POURQUOI UN SECOND MENU, PAS UNE ENTRÉE AJOUTÉE AU PREMIER
     * Signalé le 14/09 : le premier tableau de bord professionnel avait été
     * construit comme un écran à part, sans rapport avec l'entrée
     * « Tableau de bord » — déjà présente, mais morte (route null) — du menu
     * ci-dessus. La corriger en pointant simplement cette entrée vers
     * `/pro/tableau-de-bord` aurait laissé les AUTRES écrans pro (demandes
     * reçues, abonnement, fiche professionnelle) toujours hors du menu.
     * `/pro/*` est un espace suffisamment différent de `/compte/*`
     * (activité professionnelle, pas de loisirs personnels) pour mériter son
     * propre menu plutôt que de faire grossir encore la liste maquettée à 9
     * entrées de menu() — mais la même coquille visuelle (account/_layout,
     * account/_sidebar) : le prestataire n'a jamais l'impression de changer
     * d'application.
     *
     * @return list<array{icon: string, title: string, subtitle: string, route: string|null, badge: string|false}>
     */
    public static function providerMenu(): array
    {
        return [
            ['icon' => 'grid', 'title' => 'Tableau de bord', 'subtitle' => 'Aperçu de votre activité professionnelle', 'route' => 'app_pro_dashboard', 'badge' => false],
            ['icon' => 'receipt', 'title' => 'Demandes reçues', 'subtitle' => 'Répondre par un devis', 'route' => 'app_pro_requests', 'badge' => 'requests'],
            // Nouvel item (Lot G, §14 du CDC) : même route que côté client, la
            // conversation ne dépend pas du chapeau porté pour la consulter.
            ['icon' => 'mail', 'title' => 'Messages', 'subtitle' => 'Conversations avec vos clients', 'route' => 'app_account_messages', 'badge' => 'messages'],
            ['icon' => 'card', 'title' => 'Abonnement', 'subtitle' => 'Votre offre et sa facturation', 'route' => 'app_pro_subscription', 'badge' => false],
            ['icon' => 'badge_check', 'title' => 'Ma fiche professionnelle', 'subtitle' => 'Ce que voient vos clients', 'route' => 'app_pro_profile_edit', 'badge' => false],
            // Nouvel item (Lot H, §16.2 du CDC) : Review dépendait encore du
            // catalogue à réservation directe en pause, aucun écran ici.
            ['icon' => 'star', 'title' => 'Avis reçus', 'subtitle' => 'Ce que vos clients disent de vous', 'route' => 'app_pro_reviews', 'badge' => 'reviews'],
            ['icon' => 'heart', 'title' => 'Favoris', 'subtitle' => 'Vos activités favorites', 'route' => 'app_account_favorites', 'badge' => false],
            ['icon' => 'bell', 'title' => 'Notifications', 'subtitle' => 'Vos notifications et alertes', 'route' => 'app_account_notifications', 'badge' => 'notifications'],
            ['icon' => 'logout', 'title' => 'Déconnexion', 'subtitle' => 'Se déconnecter', 'route' => 'app_account_logout_confirm', 'badge' => false, 'section' => ''],
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
}
