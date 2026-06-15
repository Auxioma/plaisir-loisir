<?php

declare(strict_types=1);

namespace App\Tests\Catalog\Entity;

use App\Catalog\Entity\Category;
use PHPUnit\Framework\TestCase;

final class CategoryTest extends TestCase
{
    public function testDefaults(): void
    {
        $category = new Category();

        self::assertSame(0, $category->getPosition());
        self::assertCount(0, $category->getChildren());
    }

    public function testAddChildLinksParentAndAvoidsDuplicates(): void
    {
        $parent = new Category();
        $child = new Category();

        $parent->addChild($child);
        $parent->addChild($child); // ajout en double : ignoré

        self::assertCount(1, $parent->getChildren());
        self::assertSame($parent, $child->getParent());
    }

    public function testRemoveChildDetachesParent(): void
    {
        $parent = new Category();
        $child = new Category();
        $parent->addChild($child);

        $parent->removeChild($child);

        self::assertCount(0, $parent->getChildren());
        self::assertNull($child->getParent());
    }
}
