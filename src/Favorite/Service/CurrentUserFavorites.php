<?php

declare(strict_types=1);

namespace App\Favorite\Service;

use App\Favorite\Repository\FavoriteRepository;
use App\User\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Les favoris du visiteur en cours, pour colorer les cœurs d'une grille.
 *
 * Une seule requête par page, dont le résultat est retenu le temps de la
 * requête HTTP : sans cela, l'accueil connecté — qui affiche plusieurs grilles
 * — interrogerait la base autant de fois qu'il y a de sections.
 *
 * Un visiteur non connecté n'a pas de favoris : on ne touche pas la base.
 */
final class CurrentUserFavorites
{
    /** @var array{services: list<string>, destinations: list<string>}|null */
    private ?array $cache = null;

    public function __construct(
        private readonly Security $security,
        private readonly FavoriteRepository $favorites,
    ) {
    }

    /**
     * @return list<string>
     */
    public function activitySlugs(): array
    {
        return $this->load()['services'];
    }

    /**
     * @return list<string>
     */
    public function destinationSlugs(): array
    {
        return $this->load()['destinations'];
    }

    /**
     * @return array{services: list<string>, destinations: list<string>}
     */
    private function load(): array
    {
        if (null !== $this->cache) {
            return $this->cache;
        }

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return $this->cache = ['services' => [], 'destinations' => []];
        }

        return $this->cache = $this->favorites->findFavoriteSlugs($user);
    }
}
