<?php

declare(strict_types=1);

namespace App\Legal\Enum;

/**
 * Forme juridique d'un prestataire professionnel.
 *
 * LA LISTE EST CELLE DU CLIENT, arrêtée le 2026-07-27 et consignée dans
 * docs/corrections-client-2026-07-27.md §2 : EI, Micro-entreprise, EURL, SARL,
 * SAS, SASU, Association, Autre. Elle remplace l'ancienne énumération
 * FiscalStatus, qui n'en proposait que trois (auto-entrepreneur, particulier,
 * professionnel) et ne correspondait à aucune demande.
 */
enum LegalForm: string
{
    case SoleTrader = 'ei';
    case MicroEnterprise = 'micro_entreprise';
    case Eurl = 'eurl';
    case Sarl = 'sarl';
    case Sas = 'sas';
    case Sasu = 'sasu';
    case Association = 'association';
    case Other = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::SoleTrader => 'EI',
            self::MicroEnterprise => 'Micro-entreprise',
            self::Eurl => 'EURL',
            self::Sarl => 'SARL',
            self::Sas => 'SAS',
            self::Sasu => 'SASU',
            self::Association => 'Association',
            self::Other => 'Autre',
        };
    }

    /**
     * Formes sans capital social : le champ n'a pas à être demandé.
     */
    public function hasShareCapital(): bool
    {
        return match ($this) {
            self::SoleTrader, self::MicroEnterprise, self::Association, self::Other => false,
            default => true,
        };
    }

    /**
     * Liste prête pour un champ de formulaire (libellé => valeur).
     *
     * @return array<string, string>
     */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->label()] = $case->value;
        }

        return $choices;
    }
}
