<?php

declare(strict_types=1);

namespace App\Tests\Favorite\Entity;

use App\Catalog\Entity\Destination;
use App\Catalog\Entity\Service;
use App\Favorite\Entity\Favorite;
use App\User\Entity\User;
use PHPUnit\Framework\TestCase;

final class FavoriteTest extends TestCase
{
    public function testForServiceTargetsTheServiceOnly(): void
    {
        $user = new User();
        $service = new Service();

        $favorite = Favorite::forService($user, $service);

        self::assertSame($user, $favorite->getUser());
        self::assertSame($service, $favorite->getService());
        self::assertNull($favorite->getDestination());
    }

    public function testForDestinationTargetsTheDestinationOnly(): void
    {
        $user = new User();
        $destination = new Destination();

        $favorite = Favorite::forDestination($user, $destination);

        self::assertSame($user, $favorite->getUser());
        self::assertSame($destination, $favorite->getDestination());
        self::assertNull($favorite->getService());
    }
}
