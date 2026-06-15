<?php

declare(strict_types=1);

namespace App\Tests\Favorite\Entity;

use App\Favorite\Entity\FavoriteList;
use App\Favorite\Entity\FavoriteShare;
use App\Favorite\Enum\ShareVisibility;
use App\User\Entity\User;
use PHPUnit\Framework\TestCase;

final class FavoriteShareTest extends TestCase
{
    public function testDefaultVisibilityIsPrivate(): void
    {
        self::assertSame(ShareVisibility::Private, (new FavoriteShare())->getVisibility());
    }

    public function testFieldsAreAssignable(): void
    {
        $owner = new User();
        $list = (new FavoriteList())->setOwner($owner)->setName('Mes favoris');

        $share = (new FavoriteShare())
            ->setOwner($owner)
            ->setList($list)
            ->setToken('abc123')
            ->setVisibility(ShareVisibility::Community);

        self::assertSame($owner, $share->getOwner());
        self::assertSame($list, $share->getList());
        self::assertSame('abc123', $share->getToken());
        self::assertSame(ShareVisibility::Community, $share->getVisibility());
    }
}
