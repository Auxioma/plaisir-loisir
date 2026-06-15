<?php

declare(strict_types=1);

namespace App\Tests\Favorite\Service;

use App\Catalog\Entity\Destination;
use App\Catalog\Entity\Service;
use App\Favorite\Entity\Favorite;
use App\Favorite\Repository\FavoriteRepository;
use App\Favorite\Service\FavoriteService;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class FavoriteServiceTest extends TestCase
{
    public function testToggleServiceAddsWhenAbsent(): void
    {
        $favorites = $this->createStub(FavoriteRepository::class);
        $favorites->method('findOneByUserAndService')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(Favorite::class));
        $em->expects(self::once())->method('flush');
        $em->expects(self::never())->method('remove');

        $added = (new FavoriteService($em, $favorites))->toggleService(new User(), new Service());

        self::assertTrue($added);
    }

    public function testToggleServiceRemovesWhenPresent(): void
    {
        $existing = Favorite::forService(new User(), new Service());

        $favorites = $this->createStub(FavoriteRepository::class);
        $favorites->method('findOneByUserAndService')->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove')->with($existing);
        $em->expects(self::once())->method('flush');
        $em->expects(self::never())->method('persist');

        $added = (new FavoriteService($em, $favorites))->toggleService(new User(), new Service());

        self::assertFalse($added);
    }

    public function testToggleDestinationAddsWhenAbsent(): void
    {
        $favorites = $this->createStub(FavoriteRepository::class);
        $favorites->method('findOneByUserAndDestination')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(Favorite::class));
        $em->expects(self::once())->method('flush');

        self::assertTrue((new FavoriteService($em, $favorites))->toggleDestination(new User(), new Destination()));
    }

    public function testToggleDestinationRemovesWhenPresent(): void
    {
        $existing = Favorite::forDestination(new User(), new Destination());

        $favorites = $this->createStub(FavoriteRepository::class);
        $favorites->method('findOneByUserAndDestination')->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove')->with($existing);
        $em->expects(self::once())->method('flush');

        self::assertFalse((new FavoriteService($em, $favorites))->toggleDestination(new User(), new Destination()));
    }
}
