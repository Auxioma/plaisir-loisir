<?php

declare(strict_types=1);

namespace App\Catalog\Enum;

/**
 * Type (forme) d'une activité, tel que proposé dans le dépôt d'activité.
 *
 * À distinguer de BookingType, qui décrit le modèle de transaction (achat direct,
 * créneau, devis) et non la nature de l'activité.
 */
enum ActivityType: string
{
    case Supervised = 'supervised';   // Activité encadrée
    case Free = 'free';               // Activité libre
    case GuidedTour = 'guided_tour';  // Visite guidée
}
