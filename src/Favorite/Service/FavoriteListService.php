<?php

declare(strict_types=1);

namespace App\Favorite\Service;

use App\Catalog\Entity\Destination;
use App\Catalog\Entity\Service;
use App\Favorite\Entity\FavoriteList;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Logique métier des listes de favoris : création et garnissage.
 */
final class FavoriteListService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function createList(User $owner, string $name): FavoriteList
    {
        $list = (new FavoriteList())
            ->setOwner($owner)
            ->setName($name);

        $this->entityManager->persist($list);
        $this->entityManager->flush();

        return $list;
    }

    public function addService(FavoriteList $list, Service $service): void
    {
        $list->addService($service);
        $this->entityManager->flush();
    }

    public function removeService(FavoriteList $list, Service $service): void
    {
        $list->removeService($service);
        $this->entityManager->flush();
    }

    public function addDestination(FavoriteList $list, Destination $destination): void
    {
        $list->addDestination($destination);
        $this->entityManager->flush();
    }

    public function removeDestination(FavoriteList $list, Destination $destination): void
    {
        $list->removeDestination($destination);
        $this->entityManager->flush();
    }
}
