<?php

declare(strict_types=1);

namespace App\Admin\Enum;

/**
 * Type de contenu signalé (§16.4 du CDC).
 *
 * Message, Avis et Photo sont repris tels quels dans le CDC alors qu'aucun
 * écran ne les affiche encore (messagerie et dépôt d'avis non commencés,
 * §14 et §16.2) : le modèle reste complet, seuls « Profil » (fiche publique
 * d'un professionnel) et « Activité » (activité privée) ont un vrai bouton
 * « Signaler » aujourd'hui — voir ReportController.
 */
enum ReportSubjectType: string
{
    case Profile = 'profile';
    case Message = 'message';
    case Review = 'review';
    case Activity = 'activity';
    case Photo = 'photo';
    case Behavior = 'behavior';
}
