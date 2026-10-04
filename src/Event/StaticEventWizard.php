<?php

declare(strict_types=1);

namespace App\Event;

/**
 * Contenus éditoriaux de l'assistant « Créer un événement » — maquettes
 * docs/maquettes/creation_evenements (04/10) : étapes, conseils, formats,
 * catégories (icône et texte d'exemple ; les catégories elles-mêmes sont des
 * EventCategory en base), listes de choix.
 */
final class StaticEventWizard
{
    /** @return list<array{title: string, subtitle: string, icon: string}> */
    public static function steps(): array
    {
        return [
            ['title' => "Détails de l'événement", 'subtitle' => 'Informations principales', 'icon' => 'calendar'],
            ['title' => 'Date et heure', 'subtitle' => "Quand aura lieu l'événement", 'icon' => 'clock'],
            ['title' => 'Lieu', 'subtitle' => "Où se déroulera l'événement", 'icon' => 'pin'],
            ['title' => 'Catégorie', 'subtitle' => 'Choisissez une catégorie', 'icon' => 'tag'],
            ['title' => 'Image et description', 'subtitle' => 'Ajoutez une image et décrivez', 'icon' => 'image'],
            ['title' => 'Options et paramètres', 'subtitle' => "Autres options de l'événement", 'icon' => 'gear'],
            ['title' => 'Inviter des personnes', 'subtitle' => 'Invitez vos amis (facultatif)', 'icon' => 'users'],
            ['title' => 'Publier', 'subtitle' => 'Vérifiez et publiez', 'icon' => 'send'],
        ];
    }

    /**
     * Encart de conseils de la colonne de droite, par étape.
     *
     * @return array{title: string, items: list<string>}
     */
    public static function advice(int $step): array
    {
        return match ($step) {
            2 => ['title' => 'Conseils pour choisir la bonne date', 'items' => ['Vérifiez les événements locaux le même jour', 'Évitez les jours fériés et ponts', 'Le week-end attire généralement plus de participants', "Prévoyez suffisamment de temps pour l'organisation"]],
            3 => ['title' => 'Conseils pour bien choisir le lieu', 'items' => ['Choisissez un lieu facilement accessible', 'Vérifiez les transports et le stationnement', 'Pensez au confort et à la météo', 'Assurez-vous que le lieu correspond à votre activité', 'Respectez les règles du lieu (réservations, autorisations…)']],
            4 => ['title' => 'Conseils pour bien choisir la catégorie', 'items' => ['Choisissez la catégorie la plus précise possible', 'Cela aide les autres à trouver votre événement', 'Vous pourrez la modifier plus tard si besoin', 'Une bonne catégorie = plus de visibilité !']],
            5 => ['title' => 'Conseils pour une belle présentation', 'items' => ['Utilisez une image de haute qualité et attrayante', "Montrez l'ambiance ou le lieu de l'événement", 'Soyez clair et précis dans votre description', 'Mettez en avant les points forts', 'Donnez toutes les informations utiles']],
            6 => ['title' => 'Conseils pour bien paramétrer', 'items' => ["Choisissez le bon type d'événement", 'Définissez une limite adaptée si besoin', 'Activez les rappels pour plus de participation', 'Vérifiez la confidentialité selon votre choix', 'Vous pourrez modifier ces paramètres plus tard']],
            7 => ['title' => 'Conseils pour inviter', 'items' => ['Invitez vos amis proches pour plus de fun', "Plus il y a de participants, plus l'événement est animé", "Vous pouvez inviter jusqu'à 200 personnes", 'Les invités recevront une notification']],
            8 => ['title' => 'Conseils avant de publier', 'items' => ['Vérifiez toutes les informations de votre événement', "Assurez-vous que la date et l'heure sont correctes", 'Invitez vos amis pour plus de participants', "Ajoutez une belle image pour attirer l'attention"]],
            default => ['title' => 'Conseils pour un bon événement', 'items' => ['Choisissez un titre court et accrocheur', 'Ajoutez une belle image représentative', "Soyez précis sur la date, l'heure et le lieu", 'Décrivez clairement le déroulement', 'Précisez ce que les participants doivent apporter']],
        };
    }

    /**
     * Formats d'événement (étape 1).
     *
     * @return array<string, array{label: string, text: string, icon: string, tone: string}>
     */
    public static function types(): array
    {
        return [
            'sortie' => ['label' => 'Sortie / Activité', 'text' => 'Randonnée, balade, visite, sport, activité en plein air…', 'icon' => 'tree', 'tone' => 'green'],
            'repas' => ['label' => 'Repas / Fête', 'text' => 'Dîner, barbecue, apéro, anniversaire, fête…', 'icon' => 'cheers', 'tone' => 'red'],
            'atelier' => ['label' => 'Atelier / Cours', 'text' => 'Apprentissage, DIY, créatif, formation…', 'icon' => 'palette', 'tone' => 'violet'],
            'rencontre' => ['label' => 'Rencontre / Échange', 'text' => 'Discussion, networking, échange de compétences…', 'icon' => 'users', 'tone' => 'blue'],
            'autre' => ['label' => 'Autre', 'text' => "Autre type d'événement", 'icon' => 'dots', 'tone' => 'grey'],
        ];
    }

    /**
     * Habillage des catégories de l'étape 4 (clé = slug EventCategory).
     *
     * @return array<string, array{text: string, icon: string, tone: string}>
     */
    public static function categoryLooks(): array
    {
        return [
            'plein-air' => ['text' => 'Randonnée, balade, vélo, sport, nature, aventure…', 'icon' => 'cat_hiking', 'tone' => 'green'],
            'sorties-loisir' => ['text' => 'Cinéma, musée, spectacle, visite, amusement…', 'icon' => 'camera', 'tone' => 'red'],
            'repas-gastronomie' => ['text' => 'Dîner, restaurant, barbecue, brunch, dégustation…', 'icon' => 'utensils', 'tone' => 'orange'],
            'bien-etre-sante' => ['text' => 'Yoga, méditation, sport, bien-être, détente…', 'icon' => 'leaf', 'tone' => 'green'],
            'ateliers-apprentissage' => ['text' => 'Atelier créatif, formation, DIY, langue, développement…', 'icon' => 'grad_cap', 'tone' => 'violet'],
            'soirees-fetes' => ['text' => 'Soirée entre amis, fête, anniversaire, karaoké…', 'icon' => 'globe', 'tone' => 'orange'],
            'rencontres-echanges' => ['text' => 'Networking, discussion, échange, conférence…', 'icon' => 'users', 'tone' => 'blue'],
            'culture-arts' => ['text' => 'Exposition, concert, théâtre, art, patrimoine…', 'icon' => 'palette', 'tone' => 'violet'],
            'voyages-evasion' => ['text' => 'Week-end, voyage, escapade, découverte, tourisme…', 'icon' => 'plane', 'tone' => 'blue'],
            'actions-solidaires' => ['text' => 'Bénévolat, collecte, entraide, association, solidarité…', 'icon' => 'hand_heart', 'tone' => 'red'],
            'autre' => ['text' => 'Une catégorie non listée ci-dessus', 'icon' => 'dots', 'tone' => 'grey'],
        ];
    }

    /** @return array<string, string> */
    public static function reminders(): array
    {
        return ['' => 'Aucun rappel', '1h' => '1 heure avant', '3h' => '3 heures avant', '24h' => '24 heures avant', '48h' => '48 heures avant', '1w' => '1 semaine avant'];
    }

    /** @return array<string, string> */
    public static function timezones(): array
    {
        return ['Europe/Paris' => '(GMT+01:00/+02:00) Paris, Bruxelles', 'Europe/London' => '(GMT+00:00) Londres', 'Africa/Dakar' => '(GMT+00:00) Dakar', 'Africa/Casablanca' => '(GMT+01:00) Casablanca', 'Indian/Reunion' => '(GMT+04:00) La Réunion', 'America/Martinique' => '(GMT-04:00) Martinique', 'America/Montreal' => '(GMT-05:00) Montréal'];
    }

    /** @return list<int> */
    public static function capacities(): array
    {
        return [5, 10, 15, 20, 30, 50, 75, 100, 150, 200];
    }

    /**
     * Choix du menu « Limite de participants » (valeur envoyée => libellé).
     *
     * Construit ici et non en Twig : le filtre `merge` repose sur
     * array_merge, qui renumérote les clés numériques — « 20 participants »
     * partait alors avec la valeur 3 et l'étape 6 refusait la saisie.
     *
     * @return array<string|int, string>
     */
    public static function capacityOptions(): array
    {
        $options = ['unlimited' => 'Illimité'];
        foreach (self::capacities() as $n) {
            $options[$n] = $n.' participants';
        }

        return $options;
    }
}
