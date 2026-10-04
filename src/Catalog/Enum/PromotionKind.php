<?php

declare(strict_types=1);

namespace App\Catalog\Enum;

/**
 * Type d'une offre promotionnelle (maquette profil_Offres&Promotions_professionnel).
 */
enum PromotionKind: string
{
    case Reduction = 'reduction';
    case Special = 'special';
    case TwoForOne = 'two_for_one';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Reduction => 'Réductions',
            self::Special => 'Offres spéciales',
            self::TwoForOne => '2 pour 1',
            self::Other => 'Autres',
        };
    }
}
