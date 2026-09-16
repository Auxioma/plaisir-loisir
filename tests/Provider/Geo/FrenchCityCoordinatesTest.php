<?php

declare(strict_types=1);

namespace App\Tests\Provider\Geo;

use App\Provider\Geo\FrenchCityCoordinates;
use PHPUnit\Framework\TestCase;

final class FrenchCityCoordinatesTest extends TestCase
{
    public function testKnownCityIsCaseAndAccentInsensitive(): void
    {
        $expected = FrenchCityCoordinates::coordinatesFor('paris');

        self::assertNotNull($expected);
        self::assertSame($expected, FrenchCityCoordinates::coordinatesFor('PARIS'));
        self::assertSame($expected, FrenchCityCoordinates::coordinatesFor('  Paris  '));
    }

    public function testAccentedCityNameResolvesToTheSameCoordinates(): void
    {
        self::assertSame(
            FrenchCityCoordinates::coordinatesFor('besancon'),
            FrenchCityCoordinates::coordinatesFor('Besançon'),
        );
    }

    public function testUnknownCityReturnsNull(): void
    {
        self::assertNull(FrenchCityCoordinates::coordinatesFor('Un Hameau Inconnu Improbable'));
    }

    public function testEmptyOrNullCityReturnsNull(): void
    {
        self::assertNull(FrenchCityCoordinates::coordinatesFor(null));
        self::assertNull(FrenchCityCoordinates::coordinatesFor(''));
        self::assertNull(FrenchCityCoordinates::coordinatesFor('   '));
    }
}
