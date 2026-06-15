<?php

declare(strict_types=1);

namespace App\Tests\User\Entity;

use App\User\Entity\Address;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testGetRolesAlwaysContainsRoleUser(): void
    {
        self::assertSame(['ROLE_USER'], (new User())->getRoles());
    }

    public function testGetRolesAppendsRoleUserWithoutDuplicates(): void
    {
        $user = new User();
        // On fournit volontairement ROLE_USER en double : il ne doit pas se répéter.
        $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);

        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
    }

    public function testGetUserIdentifierIsTheEmail(): void
    {
        $user = (new User())->setEmail('alice@example.com');

        self::assertSame('alice@example.com', $user->getUserIdentifier());
    }

    public function testDefaultStatusIsPending(): void
    {
        self::assertSame(UserStatus::Pending, (new User())->getStatus());
    }

    public function testAddAddressLinksBothSidesAndAvoidsDuplicates(): void
    {
        $user = new User();
        $address = new Address();

        $user->addAddress($address);
        $user->addAddress($address); // ajout en double : ignoré

        self::assertCount(1, $user->getAddresses());
        self::assertTrue($user->getAddresses()->contains($address));
        self::assertSame($user, $address->getUser());
    }

    public function testRemoveAddressDetachesBothSides(): void
    {
        $user = new User();
        $address = new Address();
        $user->addAddress($address);

        $user->removeAddress($address);

        self::assertCount(0, $user->getAddresses());
        self::assertNull($address->getUser());
    }

    public function testEraseCredentialsIsHarmless(): void
    {
        // Aucune donnée sensible temporaire n'est conservée : l'appel ne doit rien casser.
        $this->expectNotToPerformAssertions();

        (new User())->eraseCredentials();
    }
}
