<?php

declare(strict_types=1);

namespace App\Catalog;

/**
 * Textes et listes de l'assistant « Créer une activité » de l'espace pro
 * (05/10). Pas de maquette dédiée : l'assistant reprend les composants de
 * celui des événements (maquettes creation_evenements) et couvre tout ce
 * que la fiche activité publique affiche.
 */
final class StaticActivityWizard
{
    /** @return list<array{title: string, subtitle: string, icon: string}> */
    public static function steps(): array
    {
        return [
            ['title' => 'Informations', 'subtitle' => 'Titre, catégorie, public', 'icon' => 'edit'],
            ['title' => 'Lieu', 'subtitle' => 'Adresse et rendez-vous', 'icon' => 'pin'],
            ['title' => 'Photos et description', 'subtitle' => 'Images et présentation', 'icon' => 'camera'],
            ['title' => 'Déroulé et conditions', 'subtitle' => 'Durée, inclus, à prévoir', 'icon' => 'clock'],
            ['title' => 'Tarifs et réservation', 'subtitle' => 'Formules, annulation', 'icon' => 'euro'],
            ['title' => 'Vérification', 'subtitle' => 'Relire et envoyer', 'icon' => 'check'],
        ];
    }

    /** @return array{title: string, items: list<string>} */
    public static function advice(int $step): array
    {
        return match ($step) {
            1 => ['title' => 'Un titre qui donne envie', 'items' => ['Nommez l’activité et le lieu : « Descente en canoë dans les gorges de l’Ardèche »', 'L’accroche résume l’expérience en une phrase', 'Précisez le niveau et les langues : cela rassure']],
            2 => ['title' => 'Un lieu facile à trouver', 'items' => ['Choisissez l’adresse dans la liste : elle place l’activité sur la carte', 'Le lieu affiché (région, site) apparaît sur les cartes du catalogue', 'Décrivez le point de rendez-vous précisément']],
            3 => ['title' => 'Des photos qui vendent', 'items' => ['Une photo principale lumineuse, au format paysage', 'Montrez l’activité en action, avec des participants', 'Une description claire : déroulé, ambiance, encadrement']],
            4 => ['title' => 'Pas de mauvaise surprise', 'items' => ['Listez ce qui est inclus et ce qui ne l’est pas', 'Indiquez ce qu’il faut apporter', 'Précisez les contre-indications éventuelles']],
            5 => ['title' => 'Des tarifs lisibles', 'items' => ['Une formule de base, puis des options si besoin', 'Prix par personne, par groupe ou forfait', 'Une annulation souple augmente les réservations']],
            default => ['title' => 'Avant l’envoi', 'items' => ['Relisez chaque étape', 'Notre équipe valide l’activité sous 24 h', 'Vous pourrez la modifier à tout moment']],
        };
    }

    /** @return array<string, string> */
    public static function activityTypes(): array
    {
        return ['supervised' => 'Activité encadrée', 'guided_tour' => 'Visite guidée', 'free' => 'Activité libre'];
    }

    /** @return array<string, string> */
    public static function levels(): array
    {
        return ['all_levels' => 'Tous niveaux', 'beginner' => 'Débutant', 'intermediate' => 'Intermédiaire', 'advanced' => 'Confirmé'];
    }

    /** @return list<string> */
    public static function languages(): array
    {
        return ['Français', 'Anglais', 'Espagnol', 'Allemand', 'Italien', 'Néerlandais', 'Langue des signes'];
    }

    /** @return array<string, string> */
    public static function openingPeriods(): array
    {
        return ['all_year' => 'Toute l’année', 'spring_summer' => 'Printemps - Été', 'autumn_winter' => 'Automne - Hiver'];
    }

    /**
     * Durées proposées (minutes => libellé).
     *
     * @return array<int, string>
     */
    public static function durations(): array
    {
        $out = [];
        foreach ([30, 45, 60, 90, 120, 150, 180, 240, 300, 360, 480, 600] as $m) {
            $out[$m] = self::durationLabel($m);
        }
        $out[1440] = 'Journée entière';

        return $out;
    }

    public static function durationLabel(int $minutes): string
    {
        if ($minutes >= 1440) {
            return 'Journée';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $h > 0 ? $h.'h'.($m > 0 ? sprintf('%02d', $m) : '') : $m.' min';
    }

    /** @return array<string, string> */
    public static function pricingUnits(): array
    {
        return ['per_person' => 'Par personne', 'per_group' => 'Par groupe', 'flat_rate' => 'Forfait'];
    }

    /** @return array<string, string> */
    public static function bookingTypes(): array
    {
        return [
            'calendar' => 'Sur créneaux : le client choisit parmi les créneaux de votre calendrier',
            'service_product' => 'Date libre : le client propose une date, vous la confirmez',
        ];
    }
}
