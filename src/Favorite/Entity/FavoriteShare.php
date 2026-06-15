<?php

declare(strict_types=1);

namespace App\Favorite\Entity;

use App\Favorite\Enum\ShareVisibility;
use App\Favorite\Repository\FavoriteShareRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Partage d'une liste de favoris via un lien à jeton, avec un niveau de visibilité.
 */
#[ORM\Entity(repositoryClass: FavoriteShareRepository::class)]
#[ORM\HasLifecycleCallbacks]
class FavoriteShare
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\ManyToOne(targetEntity: FavoriteList::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?FavoriteList $list = null;

    /**
     * Jeton aléatoire qui identifie le lien de partage.
     */
    #[ORM\Column(length: 64, unique: true)]
    private string $token;

    #[ORM\Column(enumType: ShareVisibility::class)]
    private ShareVisibility $visibility = ShareVisibility::Private;

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getList(): ?FavoriteList
    {
        return $this->list;
    }

    public function setList(?FavoriteList $list): static
    {
        $this->list = $list;

        return $this;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function getVisibility(): ShareVisibility
    {
        return $this->visibility;
    }

    public function setVisibility(ShareVisibility $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }
}
