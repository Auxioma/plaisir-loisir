<?php

declare(strict_types=1);

namespace App\Favorite\Twig;

use App\Favorite\Entity\Favorite;
use App\Favorite\Repository\FavoriteRepository;
use App\User\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `is_favorite(type, clé)` dans les gabarits : le cœur d'une carte de
 * l'univers Event s'affiche plein si l'élément est déjà en favori.
 *
 * Types : 'evenement' et 'groupe' (clé = slug), 'activite-privee' et
 * 'organisateur' (clé = identifiant). Les favoris de la personne connectée
 * sont lus UNE fois par requête, quelle que soit la taille de la grille.
 */
final class FavoriteExtension extends AbstractExtension
{
    /** @var array<string, array<string, true>>|null */
    private ?array $keys = null;

    public function __construct(
        private readonly Security $security,
        private readonly FavoriteRepository $favorites,
    ) {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('is_favorite', $this->isFavorite(...))];
    }

    public function isFavorite(string $type, ?string $key): bool
    {
        return null !== $key && isset($this->keys()[$type][$key]);
    }

    /**
     * @return array<string, array<string, true>>
     */
    private function keys(): array
    {
        if (null !== $this->keys) {
            return $this->keys;
        }

        $this->keys = [];
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $this->keys;
        }

        foreach ($this->favorites->findBy(['user' => $user]) as $favorite) {
            /* @var Favorite $favorite */
            match (true) {
                null !== $favorite->getDestination() => $this->keys['destination'][$favorite->getDestination()->getSlug()] = true,
                null !== $favorite->getService() => $this->keys['activite'][$favorite->getService()->getSlug()] = true,
                null !== $favorite->getEvent() => $this->keys['evenement'][$favorite->getEvent()->getSlug()] = true,
                null !== $favorite->getGroup() => $this->keys['groupe'][$favorite->getGroup()->getSlug()] = true,
                null !== $favorite->getPrivateActivity() => $this->keys['activite-privee'][(string) $favorite->getPrivateActivity()->getId()] = true,
                null !== $favorite->getOrganizer() => $this->keys['organisateur'][(string) $favorite->getOrganizer()->getId()] = true,
                default => null,
            };
        }

        return $this->keys;
    }
}
