<?php

declare(strict_types=1);

namespace App\User;

use App\Provider\StaticProviderSpace;

/**
 * Menus de l'espace compte (client et pro) : configuration d'interface, pas
 * des données. Les données de démonstration (utilisateur « Thomas Martin »,
 * liste « Alsace - 2026 »…) ont été retirées le 09/11.
 */
final class StaticAccount
{
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
        // Menu réorganisé par usage (13/10, retour client n° 29) : ce que je
        // fais (sorties), avec qui j'échange, puis mon compte. Les titres
        // disent ce que l'on trouve, sans jargon interne.
        return [
            ['icon' => 'grid', 'title' => 'Tableau de bord', 'subtitle' => 'Mon résumé', 'route' => 'app_account_dashboard', 'badge' => false],
            ['icon' => 'calendar', 'title' => 'Mes réservations', 'subtitle' => 'Activités payantes réservées', 'route' => 'app_account_history', 'badge' => false, 'section' => 'Mes sorties'],
            ['icon' => 'edit', 'title' => 'Mes activités gratuites', 'subtitle' => 'Créées et inscriptions', 'route' => 'app_account_private_activities', 'badge' => false],
            // Retours client du 11/10 : la page n'était joignable qu'après la création d'un événement.
            ['icon' => 'calendar_check', 'title' => 'Mes événements', 'subtitle' => 'Organisés et inscriptions', 'route' => 'app_account_events', 'badge' => false],
            ['icon' => 'camera', 'title' => 'Mes albums photos', 'subtitle' => 'Souvenirs de vos sorties', 'route' => 'app_account_albums', 'badge' => false],
            ['icon' => 'heart', 'title' => 'Favoris', 'subtitle' => 'Activités et destinations gardées', 'route' => 'app_account_favorites', 'badge' => false],
            ['icon' => 'mail', 'title' => 'Messages', 'subtitle' => 'Membres et professionnels', 'route' => 'app_account_messages', 'badge' => 'messages', 'section' => 'Échanges'],
            ['icon' => 'bell', 'title' => 'Notifications', 'subtitle' => 'Alertes et réglages', 'route' => 'app_account_notifications', 'badge' => 'notifications'],
            ['icon' => 'receipt', 'title' => 'Mes demandes de devis', 'subtitle' => 'Réponses des professionnels', 'route' => 'app_account_requests', 'badge' => false],
            ['icon' => 'person', 'title' => 'Informations personnelles', 'subtitle' => 'Identité et profil public', 'route' => 'app_account_profile', 'badge' => false, 'section' => 'Mon compte'],
            ['icon' => 'wallet', 'title' => 'Paiements', 'subtitle' => 'Moyens de paiement et factures', 'route' => 'app_account_payments', 'badge' => false],
            ['icon' => 'users', 'title' => 'Parrainage', 'subtitle' => 'Invitez vos amis', 'route' => 'app_account_referral', 'badge' => false],
            ['icon' => 'hand_heart', 'title' => 'Support', 'subtitle' => 'Mes demandes et échanges', 'route' => 'app_account_support', 'badge' => false],
            ['icon' => 'gear', 'title' => 'Paramètres du compte', 'subtitle' => 'Mot de passe, suppression', 'route' => 'app_account_settings', 'badge' => false],
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
        // Mêmes entrées que la sidebar de l'espace pro (StaticProviderSpace,
        // maquettes profil_professionnel du 02/10), pour les écrans partagés
        // affichés avec la coquille du compte (notifications, favoris…).
        $icons = ['home' => 'grid', 'edit' => 'edit', 'calendar' => 'calendar', 'calendar_days' => 'calendar', 'chat' => 'mail', 'star' => 'star', 'euro' => 'wallet', 'tag' => 'receipt', 'chart' => 'grid', 'gear' => 'gear', 'person' => 'person', 'support' => 'hand_heart'];
        $menu = [];
        foreach (StaticProviderSpace::menu() as $item) {
            $menu[] = ['icon' => $icons[$item['icon']] ?? 'grid', 'title' => $item['title'], 'subtitle' => '', 'route' => $item['route'], 'badge' => $item['badge']];
        }
        $menu[] = ['icon' => 'bell', 'title' => 'Notifications', 'subtitle' => 'Vos notifications et alertes', 'route' => 'app_account_notifications', 'badge' => 'notifications', 'section' => ''];
        $menu[] = ['icon' => 'heart', 'title' => 'Favoris', 'subtitle' => 'Vos activités favorites', 'route' => 'app_account_favorites', 'badge' => false];
        $menu[] = ['icon' => 'logout', 'title' => 'Déconnexion', 'subtitle' => 'Se déconnecter', 'route' => 'app_account_logout_confirm', 'badge' => false, 'section' => ''];

        return $menu;
    }
}
