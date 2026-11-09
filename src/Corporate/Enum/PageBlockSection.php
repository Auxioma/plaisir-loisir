<?php

declare(strict_types=1);

namespace App\Corporate\Enum;

/**
 * Emplacement d'un bloc de contenu sur les pages institutionnelles (09/11).
 *
 * Comme pour les rubriques de la FAQ, la STRUCTURE reste dans le code (chaque
 * section a sa mise en page) et le CONTENU vit en base, modifiable depuis le
 * back-office.
 */
enum PageBlockSection: string
{
    case AboutValues = 'about_values';
    case AboutTeam = 'about_team';
    case PartnerBenefits = 'partner_benefits';
    case PartnerSteps = 'partner_steps';
    case PartnerTestimonials = 'partner_testimonials';
    case PartnerArguments = 'partner_arguments';
    case CareerValues = 'career_values';
    case CareerReasons = 'career_reasons';
    case CareerTestimonials = 'career_testimonials';

    public function label(): string
    {
        return match ($this) {
            self::AboutValues => 'À propos — Nos valeurs',
            self::AboutTeam => 'À propos — Notre équipe',
            self::PartnerBenefits => 'Devenir partenaire — Pourquoi devenir partenaire ?',
            self::PartnerSteps => 'Devenir partenaire — Comment ça marche ?',
            self::PartnerTestimonials => 'Devenir partenaire — Ils nous font confiance',
            self::PartnerArguments => 'Devenir partenaire — Prêt à rejoindre l’aventure ?',
            self::CareerValues => 'Carrières — Nos valeurs',
            self::CareerReasons => 'Carrières — Pourquoi postuler chez nous ?',
            self::CareerTestimonials => 'Carrières — Témoignages des collaborateurs',
        };
    }

    /**
     * Ce que chaque champ veut dire dans cette section, pour l'aide du
     * back-office : « Titre » est un nom de personne dans l'équipe, etc.
     */
    public function isPeople(): bool
    {
        return \in_array($this, [self::AboutTeam, self::PartnerTestimonials, self::CareerTestimonials], true);
    }
}
