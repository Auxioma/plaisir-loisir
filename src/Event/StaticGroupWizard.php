<?php

declare(strict_types=1);

namespace App\Event;

/**
 * Données de démonstration du wizard « Créer un groupe » (spec Partie 2 —
 * flow Créer un groupe, 4 étapes + succès), en attendant les vraies entités.
 */
final class StaticGroupWizard
{
    /**
     * Suggestions de l'autocomplete de localisation (étape 1).
     * Coquille maquette corrigée : « Linciln » → Lincoln.
     *
     * @return list<array{label: string, short: string}>
     */
    public static function cities(): array
    {
        return [
            ['label' => 'Lille, France', 'short' => 'Lille'],
            ['label' => 'Linz, Australie', 'short' => 'Linz'],
            ['label' => 'Lisbon, Portugal', 'short' => 'Lisbon'],
            ['label' => 'Lincoln, United Kingdom', 'short' => 'Lincoln'],
        ];
    }

    /**
     * Tags « types d'événements » (étape 2). La maquette contient des
     * doublons de remplissage (« Sports & Sensations », « Musique »…) que la
     * spec demande de dédupliquer ; « Réligions » y est aussi corrigé.
     *
     * @return array{base: list<string>, extra: list<string>}
     */
    public static function tags(): array
    {
        return [
            // Liste courte (capture 5), avant le déclencheur « Plus ».
            'base' => [
                'Musique',
                'Soirées',
                'Ateliers & Créations',
                'Visites culturelles',
                'Sports & Sensations',
                'Entreprise',
            ],
            // Tags supplémentaires révélés par « Plus » (capture 6).
            'extra' => [
                'Sciences et technologie',
                'Religions et spiritualités',
                'Voyages et activités plein air',
                'Famille & éducation',
                'Mode & beauté',
                'Maison et style de vie',
                'Activités scolaires',
            ],
        ];
    }

    /**
     * Encart « Conseil » propre à chaque étape.
     */
    public static function advice(int $step): string
    {
        return match ($step) {
            1 => "Les groupes TrouveMoi se rencontrent localement, en personne. L'emplacement nous aide à vous connecter avec les personnes de votre région.",
            2 => "Soyez spécifique ! Cela nous aidera à promouvoir votre groupe auprès des bonnes personnes. Essayez de sélectionner au moins 3 sujets avant de passer à l'étape suivante.",
            3 => 'Bien que vous puissiez modifier cela plus tard, il est important de choisir un nom approprié maintenant, car votre groupe sera examiné une fois créé.',
            default => '',
        };
    }
}
