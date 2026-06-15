<?php

declare(strict_types=1);

namespace App\Favorite\Service;

use App\Favorite\Entity\FavoriteList;
use App\Favorite\Entity\FavoriteShare;
use App\Favorite\Enum\ShareVisibility;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Logique métier du partage de favoris : génère un lien à jeton avec un mode de visibilité.
 */
final class FavoriteShareService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function share(FavoriteList $list, ShareVisibility $visibility): FavoriteShare
    {
        $share = (new FavoriteShare())
            ->setOwner($list->getOwner())
            ->setList($list)
            ->setVisibility($visibility)
            ->setToken($this->generateToken());

        $this->entityManager->persist($share);
        $this->entityManager->flush();

        return $share;
    }

    public function revoke(FavoriteShare $share): void
    {
        $this->entityManager->remove($share);
        $this->entityManager->flush();
    }

    /**
     * Jeton aléatoire imprévisible (32 caractères hexadécimaux).
     */
    private function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }
}
