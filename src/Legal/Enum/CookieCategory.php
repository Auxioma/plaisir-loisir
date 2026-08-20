<?php

declare(strict_types=1);

namespace App\Legal\Enum;

/**
 * Catégories du bandeau de consentement aux cookies.
 *
 * Découpage recommandé par la CNIL. « Nécessaires » n'est pas un choix : ces
 * cookies (session, panier, jeton anti-CSRF) sont indispensables au service et
 * exemptés de consentement. Les trois autres sont refusés par défaut — un
 * consentement ne se présume pas.
 */
enum CookieCategory: string
{
    case Necessary = 'necessary';
    case Preferences = 'preferences';
    case Statistics = 'statistics';
    case Marketing = 'marketing';

    public function label(): string
    {
        return match ($this) {
            self::Necessary => 'Strictement nécessaires',
            self::Preferences => 'Préférences',
            self::Statistics => 'Mesure d\'audience',
            self::Marketing => 'Publicité et réseaux sociaux',
        };
    }

    public function isOptional(): bool
    {
        return self::Necessary !== $this;
    }

    /**
     * @return list<self>
     */
    public static function optional(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $c) => $c->isOptional()));
    }
}
