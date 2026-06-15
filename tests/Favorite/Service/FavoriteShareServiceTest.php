<?php

declare(strict_types=1);

namespace App\Tests\Favorite\Service;

use App\Favorite\Entity\FavoriteList;
use App\Favorite\Entity\FavoriteShare;
use App\Favorite\Enum\ShareVisibility;
use App\Favorite\Service\FavoriteShareService;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class FavoriteShareServiceTest extends TestCase
{
    public function testShareGeneratesTokenAndPersists(): void
    {
        $owner = new User();
        $list = (new FavoriteList())->setOwner($owner)->setName('Idées vacances');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(FavoriteShare::class));
        $em->expects(self::once())->method('flush');

        $share = (new FavoriteShareService($em))->share($list, ShareVisibility::Public);

        self::assertSame($owner, $share->getOwner());
        self::assertSame($list, $share->getList());
        self::assertSame(ShareVisibility::Public, $share->getVisibility());
        self::assertSame(32, \strlen($share->getToken()));
    }

    public function testTwoSharesGetDistinctTokens(): void
    {
        $list = (new FavoriteList())->setOwner(new User())->setName('X');

        $service = new FavoriteShareService($this->createStub(EntityManagerInterface::class));

        $first = $service->share($list, ShareVisibility::Private);
        $second = $service->share($list, ShareVisibility::Private);

        self::assertNotSame($first->getToken(), $second->getToken());
    }

    public function testRevokeRemovesShare(): void
    {
        $share = new FavoriteShare();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove')->with($share);
        $em->expects(self::once())->method('flush');

        (new FavoriteShareService($em))->revoke($share);
    }
}
