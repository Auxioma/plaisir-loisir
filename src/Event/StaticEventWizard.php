<?php

declare(strict_types=1);

namespace App\Event;

/**
 * Données statiques du wizard « Créer un événement » (8 étapes + succès).
 *
 * Le wizard est UN layout unique (stepper / carte d'étape / aperçu) dont
 * seule la colonne centrale change — voir templates/event/creer.html.twig.
 *
 * NB maquette : typos corrigées (« Acceuil » → Accueil, « memebre » →
 * membre, « 24 mai 202 » → 2024, « crée avec succés » → créé avec
 * succès…) — même décision que pour les autres flows.
 */
final class StaticEventWizard
{
    /**
     * Les 8 étapes du stepper (titre + sous-titre).
     *
     * @return list<array<string, string>>
     */
    public static function steps(): array
    {
        return [
            ['title' => "Détails de l'événement", 'subtitle' => 'Informations principales'],
            ['title' => 'Date et heure', 'subtitle' => "Quand aura lieu l'événement"],
            ['title' => 'Lieu', 'subtitle' => "Où se déroulera l'événement"],
            ['title' => 'Catégorie', 'subtitle' => 'Choisissez une catégorie'],
            ['title' => 'Image et description', 'subtitle' => 'Ajoutez une image/décrivez'],
            ['title' => 'Options et paramètres', 'subtitle' => "Autres options de l'événement"],
            ['title' => 'Inviter des personnes', 'subtitle' => 'Invitez vos amis (facultatif)'],
            ['title' => 'Publier', 'subtitle' => 'Vérifiez et publiez'],
        ];
    }

    /**
     * Card « Conseil » : intro + liste de checks, par étape.
     *
     * @return array{intro: ?string, items: list<string>}
     */
    public static function advice(int $step): array
    {
        $all = [
            1 => [null, ['Choisissez un titre court et accrocheur', 'Ajoutez une belle image représentative', "Soyez précis sur la date, l'heure et le lieu", 'Décrivez clairement le déroulement', 'Précisez ce que les participants doivent apporter']],
            2 => ['Pour choisir la bonne date vous devez:', ['Vérifiez les événements locaux le même jour', 'Évitez les jours fériés et ponts', 'Le week-end attire généralement plus de participants', "Prévoyez suffisamment de temps pour l'organisation"]],
            3 => ['Pour bien choisir le lieu:', ['Choisissez un lieu facilement accessible', 'Vérifiez les transports et le stationnement', 'Pensez au confort et à la météo', 'Assurez-vous que le lieu correspond à votre activité', 'Respectez les règles du lieu (réservations, autorisations…)']],
            4 => ['Pour bien choisir votre catégorie vous devez:', ['Choisissez la catégorie la plus précise possible', 'Cela aide les autres à trouver votre événement', 'Vous pourrez la modifier plus tard si besoin', 'Une bonne catégorie = plus de visibilité !']],
            5 => ['Pour une belle présentation vous devez:', ['Utilisez une image de haute qualité et attrayante', "Montrez l'ambiance ou le lieu de l'événement", 'Soyez clair et précis dans votre description', 'Mettez en avant les points forts', 'Donnez toutes les informations utiles']],
            6 => ['Pour paramétrer vous devez:', ["Choisissez le bon type d'événement", 'Définissez une limite adaptée si besoin', 'Activez les rappels pour plus de participation', 'Vérifiez la confidentialité selon votre choix', 'Vous pourrez modifier ces paramètres plus tard']],
            7 => ['Pour inviter vous devez:', ['Invitez vos amis proches pour plus de fun', "Plus il y a de participants, plus l'événement est animé", "Vous pouvez inviter jusqu'à 200 personnes", 'Les invités recevront une notification']],
            8 => ['Avant de publier vous devez:', ['Vérifiez toutes les informations de votre événement', "Assurez-vous que la date et l'heure sont correctes", 'Invitez vos amis pour plus de participants', "Ajoutez une belle image pour attirer l'attention"]],
        ];

        [$intro, $items] = $all[$step];

        return ['intro' => $intro, 'items' => $items];
    }

    /**
     * Étape 4 : la liste des catégories populaires.
     *
     * @return list<array<string, string>>
     */
    public static function categories(): array
    {
        return [
            ['icon' => 'bell', 'color' => 'green', 'title' => 'Activités de plein air', 'desc' => 'Randonnée, balade, vélo, sport, nature, aventure…'],
            ['icon' => 'camera', 'color' => 'red', 'title' => 'Sorties & Loisir', 'desc' => 'Cinéma, musée, spectacle, visite, amusement…'],
            ['icon' => 'utensils', 'color' => 'yellow', 'title' => 'Repas & Gastronomie', 'desc' => 'Dîner, restaurant, barbecue, brunch, dégustation…'],
            ['icon' => 'leaf', 'color' => 'green', 'title' => 'Bien-être & Santé', 'desc' => 'Yoga, méditation, sport, bien-être, détente…'],
            ['icon' => 'grad_cap', 'color' => 'blue', 'title' => 'Ateliers & Apprentissage', 'desc' => 'Atelier créatif, formation, DIY, langue, développement…'],
            ['icon' => 'cheers', 'color' => 'orange', 'title' => 'Soirées & Fêtes', 'desc' => 'Soirée entre amis, fête, anniversaire, karaoké…'],
            ['icon' => 'users', 'color' => 'blue', 'title' => 'Rencontres & Échanges', 'desc' => 'Networking, discussion, échange, conférence…'],
            ['icon' => 'palette', 'color' => 'violet', 'title' => 'Culture & Arts', 'desc' => 'Exposition, concert, théâtre, art, patrimoine…'],
            ['icon' => 'plane', 'color' => 'orange', 'title' => 'Voyages & Évasion', 'desc' => 'Week-end, voyage, escapade, découverte, tourisme…'],
            ['icon' => 'hand_heart', 'color' => 'green', 'title' => 'Actions solidaires', 'desc' => 'Bénévolat, collecte, entraide, association, solidarité…'],
            ['icon' => 'dots', 'color' => 'grey', 'title' => 'Autre', 'desc' => 'Une catégorie non listée ci-dessus'],
        ];
    }

    /**
     * Étape 7 : les suggestions de contacts.
     *
     * @return list<array<string, mixed>>
     */
    public static function contacts(): array
    {
        return [
            ['name' => 'Marie Dupont', 'friends' => 12, 'avatar' => 'images/events/avatar-marie.jpg', 'invited' => false],
            ['name' => 'Thomas Martin', 'friends' => 8, 'avatar' => 'images/events/avatar-thomas.jpg', 'invited' => false],
            ['name' => 'Sophie Bernard', 'friends' => 15, 'avatar' => 'images/events/avatar-sophie.jpg', 'invited' => false],
            ['name' => 'Lucas Petit', 'friends' => 12, 'avatar' => 'images/events/avatar-lucas.jpg', 'invited' => true],
            ['name' => 'Chloé Leroy', 'friends' => 7, 'avatar' => 'images/events/avatar-chloe.jpg', 'invited' => false],
        ];
    }
}
