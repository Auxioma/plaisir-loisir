<?php

declare(strict_types=1);

namespace App\PrivateActivity\Service;

use App\PrivateActivity\Entity\PrivateActivity;

/**
 * Image d'une activité gratuite : sa photo de couverture, à défaut une image
 * de sa catégorie. Partagée par les gabarits (private_activity_cover()) et
 * les listes du compte (Mes activités créées, albums) — ces dernières
 * ignoraient la photo choisie par l'organisateur (retour client du 07/10).
 */
final class PrivateActivityImage
{
    private const BY_CATEGORY = [
        'sports-aventures' => 'images/gifts/tile-sports.jpg',
        'natures-plein-air' => 'images/gifts/card-kayak.jpg',
        'bien-etre' => 'images/gifts/tile-bienetre.jpg',
        'cultures-decouvertes' => 'images/gifts/tile-cultures.jpg',
        'en-famille' => 'images/gifts/dest-enfants.jpg',
        'gastronomies' => 'images/gifts/tile-gastronomies.jpg',
        'ateliers-creations' => 'images/gifts/tile-ateliers.jpg',
        'soirees-evenements' => 'images/gifts/fg-diner.jpg',
    ];

    private const DEFAULT = 'images/events/ev-rando-clean.jpg';

    public static function pathFor(PrivateActivity $activity): string
    {
        $cover = $activity->getCoverImage();
        if (null !== $cover && '' !== $cover) {
            return $cover;
        }

        return self::BY_CATEGORY[$activity->getCategory()?->getSlug() ?? ''] ?? self::DEFAULT;
    }
}
