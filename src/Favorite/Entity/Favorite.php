<?php

declare(strict_types=1);

namespace App\Favorite\Entity;

use App\Catalog\Entity\Destination;
use App\Catalog\Entity\Service;
use App\Favorite\Repository\FavoriteRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Favori d'un utilisateur : pointe vers UNE activité (Service) OU UNE destination.
 *
 * L'invariant « exactement une cible » est garanti par les constructeurs nommés
 * (forService / forDestination), et l'unicité par utilisateur évite les doublons.
 */
#[ORM\Entity(repositoryClass: FavoriteRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_favorite_user_service', columns: ['user_id', 'service_id'])]
#[ORM\UniqueConstraint(name: 'uniq_favorite_user_destination', columns: ['user_id', 'destination_id'])]
#[ORM\HasLifecycleCallbacks]
class Favorite
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Service $service = null;

    #[ORM\ManyToOne(targetEntity: Destination::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Destination $destination = null;

    private function __construct()
    {
    }

    public static function forService(User $user, Service $service): self
    {
        $favorite = new self();
        $favorite->user = $user;
        $favorite->service = $service;

        return $favorite;
    }

    public static function forDestination(User $user, Destination $destination): self
    {
        $favorite = new self();
        $favorite->user = $user;
        $favorite->destination = $destination;

        return $favorite;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function getDestination(): ?Destination
    {
        return $this->destination;
    }
}
