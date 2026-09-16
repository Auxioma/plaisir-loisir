<?php

declare(strict_types=1);

namespace App\Provider\Geo;

/**
 * Distance à vol d'oiseau entre deux points, en kilomètres — formule de
 * Haversine, précise à moins de 0,5 % près pour des distances régionales
 * (largement suffisant pour un rayon de recherche exprimé en dizaines de
 * kilomètres).
 */
final class HaversineDistance
{
    private const EARTH_RADIUS_KM = 6371.0;

    public static function betweenKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) ** 2 + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }
}
