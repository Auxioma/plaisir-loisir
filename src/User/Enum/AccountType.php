<?php

declare(strict_types=1);

namespace App\User\Enum;

/**
 * Nature du compte choisie sur l'écran d'entrée « Pro Prestataire / Client »
 * (maquette register01, frame Figma 849:69450).
 *
 * Les valeurs sont celles qui circulent déjà dans les URL de la maquette
 * (« /register?type=pro »), pour ne pas avoir à traduire d'un côté ou de
 * l'autre.
 */
enum AccountType: string
{
    case Client = 'client';
    case Provider = 'pro';

    /**
     * Lecture tolérante d'une valeur venue de l'URL ou d'un champ posté.
     *
     * Toute valeur inconnue — ou absente — retombe sur « client ». Un
     * paramètre bricolé à la main ne doit jamais provoquer d'erreur, et le
     * compte le moins privilégié est le bon défaut.
     */
    public static function fromInput(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Client;
    }

    public function isProvider(): bool
    {
        return self::Provider === $this;
    }
}
