<?php

declare(strict_types=1);

namespace App\Provider;

/**
 * Menu et contenus éditoriaux de l'espace professionnel (maquettes
 * docs/maquettes/profil_professionnel, 02/10).
 */
final class StaticProviderSpace
{
    /**
     * Les douze entrées de la sidebar, dans l'ordre de la maquette.
     * `badge` : compteur rouge (`messages` = messages non lus).
     *
     * @return list<array{icon: string, title: string, route: string, badge: string|false}>
     */
    public static function menu(): array
    {
        return [
            ['icon' => 'home', 'title' => 'Tableau de bord', 'route' => 'app_pro_dashboard', 'badge' => false],
            ['icon' => 'edit', 'title' => 'Mes activités', 'route' => 'app_pro_activities', 'badge' => false],
            ['icon' => 'calendar', 'title' => 'Réservations', 'route' => 'app_pro_bookings', 'badge' => false],
            ['icon' => 'calendar_days', 'title' => 'Calendrier', 'route' => 'app_pro_calendar', 'badge' => false],
            ['icon' => 'chat', 'title' => 'Clients & Messages', 'route' => 'app_pro_messages', 'badge' => 'messages'],
            ['icon' => 'star', 'title' => 'Avis & Évaluations', 'route' => 'app_pro_reviews', 'badge' => false],
            ['icon' => 'euro', 'title' => 'Revenus & Paiements', 'route' => 'app_pro_revenue', 'badge' => false],
            ['icon' => 'tag', 'title' => 'Offres & Promotions', 'route' => 'app_pro_promotions', 'badge' => false],
            ['icon' => 'chart', 'title' => 'Statistiques', 'route' => 'app_pro_stats', 'badge' => false],
            ['icon' => 'gear', 'title' => 'Paramètres', 'route' => 'app_pro_settings', 'badge' => false],
            ['icon' => 'person', 'title' => 'Mon profil', 'route' => 'app_pro_my_profile', 'badge' => false],
            ['icon' => 'support', 'title' => 'Support', 'route' => 'app_pro_support', 'badge' => false],
        ];
    }

    /**
     * Conseils affichés dans les encarts (« Conseils pour vos activités »,
     * « … pour plus d'avis », « … pour vos offres »).
     *
     * @return array<string, list<array{title: string, text: string}>>
     */
    public static function tips(): array
    {
        return [
            'activities' => [
                ['title' => 'Ajoutez des photos de qualité', 'text' => 'Les activités avec de belles photos reçoivent jusqu\'à 3x plus de réservations.'],
                ['title' => 'Soignez vos descriptions', 'text' => 'Une description détaillée rassure vos clients et améliore votre visibilité.'],
                ['title' => 'Mettez à jour régulièrement', 'text' => 'Des informations à jour augmentent la confiance et vos avis positifs.'],
            ],
            'reviews' => [
                ['title' => 'Envoyez un email de suivi', 'text' => 'Invitez vos clients à laisser un avis après l\'activité.'],
                ['title' => 'Ajoutez un QR code', 'text' => 'Affichez un QR code vers votre fiche sur vos supports pour faciliter le dépôt d\'avis.'],
                ['title' => 'Répondez à tous les avis', 'text' => 'Montrez votre engagement en répondant aux avis de vos clients.'],
            ],
            'offers' => [
                ['title' => 'Utilisez des visuels attractifs', 'text' => 'Les offres avec images génèrent 3x plus de clics.'],
                ['title' => 'Créez un sentiment d\'urgence', 'text' => 'Ajoutez des dates limites pour inciter à réserver.'],
                ['title' => 'Testez et ajustez', 'text' => 'Analysez les performances et optimisez vos offres.'],
            ],
        ];
    }
}
