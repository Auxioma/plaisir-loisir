<?php

declare(strict_types=1);

namespace App\Tests\Favorite\Service;

use App\Catalog\Entity\Service;
use App\Favorite\Entity\FavoriteList;
use App\Favorite\Service\FavoriteListService;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class FavoriteListServiceTest extends TestCase
{
    public function testCreateListPersistsAndReturnsNamedList(): void
    {
        $owner = new User();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(FavoriteList::class));
        $em->expects(self::once())->method('flush');

        $list = (new FavoriteListService($em))->createList($owner, 'Idées week-end');

        self::assertSame($owner, $list->getOwner());
        self::assertSame('Idées week-end', $list->getName());
    }

    public function testAddServiceAddsToListAndFlushes(): void
    {
        $list = new FavoriteList();
        $service = new Service();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');
        $em->expects(self::never())->method('persist');

        (new FavoriteListService($em))->addService($list, $service);

        self::assertCount(1, $list->getServices());
        self::assertTrue($list->getServices()->contains($service));
    }
}
