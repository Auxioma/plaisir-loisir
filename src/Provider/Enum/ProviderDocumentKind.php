<?php

declare(strict_types=1);

namespace App\Provider\Enum;

/**
 * Nature d'une pièce justificative déposée par un professionnel.
 *
 * La maquette d'inscription (étape 2/2) nomme explicitement deux pièces — la
 * licence d'exploitation et le certificat de sécurité alimentaire — puis offre
 * un « Ajouter un autre document » sans en préciser la nature. D'où le
 * troisième cas, volontairement générique : c'est le prestataire qui sait ce
 * qu'il dépose, pas nous.
 */
enum ProviderDocumentKind: string
{
    case OperatingLicence = 'operating_licence';
    case FoodSafetyCertificate = 'food_safety_certificate';
    // Pièces de l'espace pro « Mon profil » (02/10).
    case Identity = 'identity';
    case Insurance = 'insurance';
    case Diploma = 'diploma';
    case FirstAid = 'first_aid';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::OperatingLicence => "Licence d'exploitation",
            self::FoodSafetyCertificate => 'Certificat de sécurité alimentaire',
            self::Identity => "Pièce d'identité",
            self::Insurance => 'Assurance professionnelle',
            self::Diploma => 'Diplôme / qualification',
            self::FirstAid => 'Premiers secours',
            self::Other => 'Autre document',
        };
    }
}
