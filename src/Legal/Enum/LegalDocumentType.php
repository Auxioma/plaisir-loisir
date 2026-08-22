<?php

declare(strict_types=1);

namespace App\Legal\Enum;

/**
 * Nature d'un document juridique publié par la plateforme.
 *
 * Les cinq documents attendus par la spécification institutionnelle
 * (assets/corporate/TrouveMoi-spec-corporate.md, écrans 7 et 8) et par le
 * formulaire d'inscription, qui fait accepter les conditions générales et la
 * politique de confidentialité.
 */
enum LegalDocumentType: string
{
    /** Conditions générales d'utilisation — le contrat entre la plateforme et ses membres. */
    case TermsOfService = 'terms_of_service';

    /** Conditions générales de vente — la réservation et le paiement des prestations. */
    case TermsOfSale = 'terms_of_sale';

    /** Politique de confidentialité — traitement des données personnelles (RGPD). */
    case PrivacyPolicy = 'privacy_policy';

    /** Mentions légales — éditeur, hébergeur, directeur de la publication. */
    case LegalNotice = 'legal_notice';

    /** Politique de gestion des cookies et traceurs. */
    case CookiePolicy = 'cookie_policy';

    public function label(): string
    {
        return match ($this) {
            self::TermsOfService => 'Conditions générales d\'utilisation',
            self::TermsOfSale => 'Conditions générales de vente',
            self::PrivacyPolicy => 'Politique de confidentialité',
            self::LegalNotice => 'Mentions légales',
            self::CookiePolicy => 'Politique de cookies',
        };
    }

    /**
     * Documents dont l'acceptation explicite est exigée à l'inscription.
     *
     * Ce sont exactement les deux que cite la case à cocher de la maquette :
     * « J'accepte les conditions générales et la politique de confidentialité ».
     *
     * @return list<self>
     */
    public static function requiredAtRegistration(): array
    {
        return [self::TermsOfService, self::PrivacyPolicy];
    }
}
