<?php

declare(strict_types=1);

namespace App\Tests\Provider\Geo;

use App\Provider\Geo\HaversineDistance;
use PHPUnit\Framework\TestCase;

final class HaversineDistanceTest extends TestCase
{
    public function testDistanceBetweenTheSamePointIsZero(): void
    {
        self::assertEqualsWithDelta(0.0, HaversineDistance::betweenKm(48.8566, 2.3522, 48.8566, 2.3522), 0.001);
    }

    /**
     * Paris ↔ Lyon : environ 392 km à vol d'oiseau (référence connue).
     */
    public function testDistanceBetweenParisAndLyonMatchesTheKnownValue(): void
    {
        $distance = HaversineDistance::betweenKm(48.8566, 2.3522, 45.7640, 4.8357);

        self::assertEqualsWithDelta(392.0, $distance, 5.0);
    }
}
