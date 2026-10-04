<?php

declare(strict_types=1);

namespace App\Legal;

use App\Corporate\StaticCorporate;
use App\Legal\Enum\LegalDocumentType;

/**
 * Texte initial de chaque document juridique (HTML à titres de niveau 2).
 *
 * Sert à la première publication (app:legal:publish) ET de repli à
 * l'affichage tant qu'aucune version n'est publiée en base : une page
 * juridique ne doit jamais s'afficher vide (constaté le 04/10 sur une base
 * fraîchement chargée).
 */
final class LegalDefaults
{
    public static function content(LegalDocumentType $type): string
    {
        return match ($type) {
            LegalDocumentType::TermsOfService => self::renderSections(StaticCorporate::cguSections()),
            LegalDocumentType::LegalNotice => self::renderSections(StaticCorporate::legalSections()),
            LegalDocumentType::PrivacyPolicy => InitialLegalTexts::privacyPolicy(),
            LegalDocumentType::TermsOfSale => InitialLegalTexts::termsOfSale(),
            LegalDocumentType::CookiePolicy => InitialLegalTexts::cookiePolicy(),
        };
    }

    /**
     * @param list<array<string, mixed>> $sections
     */
    public static function renderSections(array $sections): string
    {
        $morceaux = [];

        foreach ($sections as $section) {
            $titre = (string) ($section['title'] ?? '');
            $bloc = '' !== $titre ? '<h2>'.htmlspecialchars($titre, \ENT_QUOTES).'</h2>' : '';

            if (isset($section['intro']) && \is_string($section['intro'])) {
                $bloc .= self::paragraphe($section['intro']);
            }

            foreach ((array) ($section['paragraphs'] ?? []) as $paragraphe) {
                if (\is_string($paragraphe)) {
                    $bloc .= self::paragraphe($paragraphe);
                }
            }

            $puces = '';
            foreach ((array) ($section['items'] ?? []) as $item) {
                if (\is_string($item)) {
                    $puces .= '<li>'.htmlspecialchars($item, \ENT_QUOTES).'</li>';
                }
            }
            if ('' !== $puces) {
                $bloc .= '<ul>'.$puces.'</ul>';
            }

            if ('' !== $bloc) {
                $morceaux[] = $bloc;
            }
        }

        return implode("\n", $morceaux);
    }

    private static function paragraphe(string $texte): string
    {
        return '<p>'.htmlspecialchars($texte, \ENT_QUOTES).'</p>';
    }
}
