<?php

declare(strict_types=1);

namespace App\Favorite\Entity;

use App\Catalog\Entity\Destination;
use App\Catalog\Entity\Service;
use App\Event\Entity\Event;
use App\Event\Entity\Group;
use App\Favorite\Repository\FavoriteRepository;
use App\PrivateActivity\Entity\PrivateActivity;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Favori d'un utilisateur : pointe vers UNE seule cible parmi activité
 * (Service), destination, événement, groupe, activité privée ou organisateur
 * (ces quatre dernières ajoutées le 01/10 pour la page « Mes favoris » de la
 * maquette favoris.jpeg).
 *
 * L'invariant « exactement une cible » est garanti par les constructeurs nommés
 * (for…), et l'unicité par utilisateur évite les doublons.
 */
#[ORM\Entity(repositoryClass: FavoriteRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_favorite_user_service', columns: ['user_id', 'service_id'])]
#[ORM\UniqueConstraint(name: 'uniq_favorite_user_destination', columns: ['user_id', 'destination_id'])]
#[ORM\UniqueConstraint(name: 'uniq_favorite_user_event', columns: ['user_id', 'event_id'])]
#[ORM\UniqueConstraint(name: 'uniq_favorite_user_group', columns: ['user_id', 'group_id'])]
#[ORM\UniqueConstraint(name: 'uniq_favorite_user_private_activity', columns: ['user_id', 'private_activity_id'])]
#[ORM\UniqueConstraint(name: 'uniq_favorite_user_organizer', columns: ['user_id', 'organizer_id'])]
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

    #[ORM\ManyToOne(targetEntity: Event::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Event $event = null;

    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[ORM\JoinColumn(name: 'group_id', nullable: true, onDelete: 'CASCADE')]
    private ?Group $group = null;

    #[ORM\ManyToOne(targetEntity: PrivateActivity::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?PrivateActivity $privateActivity = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $organizer = null;

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

    public static function forEvent(User $user, Event $event): self
    {
        $favorite = new self();
        $favorite->user = $user;
        $favorite->event = $event;

        return $favorite;
    }

    public static function forGroup(User $user, Group $group): self
    {
        $favorite = new self();
        $favorite->user = $user;
        $favorite->group = $group;

        return $favorite;
    }

    public static function forPrivateActivity(User $user, PrivateActivity $activity): self
    {
        $favorite = new self();
        $favorite->user = $user;
        $favorite->privateActivity = $activity;

        return $favorite;
    }

    public static function forOrganizer(User $user, User $organizer): self
    {
        $favorite = new self();
        $favorite->user = $user;
        $favorite->organizer = $organizer;

        return $favorite;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function getGroup(): ?Group
    {
        return $this->group;
    }

    public function getPrivateActivity(): ?PrivateActivity
    {
        return $this->privateActivity;
    }

    public function getOrganizer(): ?User
    {
        return $this->organizer;
    }
}
