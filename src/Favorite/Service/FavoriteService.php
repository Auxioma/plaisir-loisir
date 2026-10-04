<?php

declare(strict_types=1);

namespace App\Favorite\Service;

use App\Catalog\Entity\Destination;
use App\Catalog\Entity\Service;
use App\Event\Entity\Event;
use App\Event\Entity\Group;
use App\Favorite\Entity\Favorite;
use App\Favorite\Repository\FavoriteRepository;
use App\PrivateActivity\Entity\PrivateActivity;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Logique métier des favoris : ajout/retrait par bascule (le cœur cliquable).
 */
final class FavoriteService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FavoriteRepository $favorites,
    ) {
    }

    /**
     * Bascule le favori sur une activité.
     *
     * @return bool true si le favori vient d'être ajouté, false s'il a été retiré
     */
    public function toggleService(User $user, Service $service): bool
    {
        $existing = $this->favorites->findOneByUserAndService($user, $service);

        if (null !== $existing) {
            $this->entityManager->remove($existing);
            $this->entityManager->flush();

            return false;
        }

        $this->entityManager->persist(Favorite::forService($user, $service));
        $this->entityManager->flush();

        return true;
    }

    /**
     * Bascule le favori sur une destination.
     *
     * @return bool true si le favori vient d'être ajouté, false s'il a été retiré
     */
    public function toggleDestination(User $user, Destination $destination): bool
    {
        $existing = $this->favorites->findOneByUserAndDestination($user, $destination);

        if (null !== $existing) {
            $this->entityManager->remove($existing);
            $this->entityManager->flush();

            return false;
        }

        $this->entityManager->persist(Favorite::forDestination($user, $destination));
        $this->entityManager->flush();

        return true;
    }

    /**
     * Bascule un favori de l'univers Event (événement, groupe, activité
     * privée, organisateur) : même règle que les deux méthodes ci-dessus.
     *
     * @param 'event'|'group'|'privateActivity'|'organizer' $field
     *
     * @return bool true si le favori vient d'être ajouté, false s'il a été retiré
     */
    public function toggle(User $user, string $field, Event|Group|PrivateActivity|User $target): bool
    {
        $existing = $this->favorites->findOneBy(['user' => $user, $field => $target]);

        if (null !== $existing) {
            $this->entityManager->remove($existing);
            $this->entityManager->flush();

            return false;
        }

        $favorite = match (true) {
            $target instanceof Event => Favorite::forEvent($user, $target),
            $target instanceof Group => Favorite::forGroup($user, $target),
            $target instanceof PrivateActivity => Favorite::forPrivateActivity($user, $target),
            default => Favorite::forOrganizer($user, $target),
        };
        $this->entityManager->persist($favorite);
        $this->entityManager->flush();

        return true;
    }
}
