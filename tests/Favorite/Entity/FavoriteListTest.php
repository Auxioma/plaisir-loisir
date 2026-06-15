<?php

declare(strict_types=1);

namespace App\Tests\Favorite\Entity;

use App\Catalog\Entity\Destination;
use App\Catalog\Entity\Service;
use App\Favorite\Entity\FavoriteList;
use App\User\Entity\User;
use PHPUnit\Framework\TestCase;

final class FavoriteListTest extends TestCase
{
    public function testBasicFieldsAndEmptyDefaults(): void
    {
        $owner = new User();
        $list = (new FavoriteList())->setOwner($owner)->setName('Été 2026');

        self::assertSame($owner, $list->getOwner());
        self::assertSame('Été 2026', $list->getName());
        self::assertCount(0, $list->getServices());
        self::assertCount(0, $list->getDestinations());
    }

    public function testAddServiceAvoidsDuplicates(): void
    {
        $list = new FavoriteList();
        $service = new Service();

        $list->addService($service);
        $list->addService($service); // doublon ignoré

        self::assertCount(1, $list->getServices());
        self::assertTrue($list->getServices()->contains($service));
    }

    public function testRemoveService(): void
    {
        $list = new FavoriteList();
        $service = new Service();
        $list->addService($service);

        $list->removeService($service);

        self::assertCount(0, $list->getServices());
    }

    public function testAddAndRemoveDestination(): void
    {
        $list = new FavoriteList();
        $destination = new Destination();

        $list->addDestination($destination);
        self::assertCount(1, $list->getDestinations());

        $list->removeDestination($destination);
        self::assertCount(0, $list->getDestinations());
    }
}
