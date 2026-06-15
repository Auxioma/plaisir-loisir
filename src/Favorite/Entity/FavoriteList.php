<?php

declare(strict_types=1);

namespace App\Favorite\Entity;

use App\Catalog\Entity\Destination;
use App\Catalog\Entity\Service;
use App\Favorite\Repository\FavoriteListRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Liste nommée d'éléments favoris créée par un utilisateur (« Vos listes »).
 * Une liste regroupe des activités et/ou des destinations.
 */
#[ORM\Entity(repositoryClass: FavoriteListRepository::class)]
#[ORM\HasLifecycleCallbacks]
class FavoriteList
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column(length: 120)]
    private string $name;

    /**
     * @var Collection<int, Service>
     */
    #[ORM\ManyToMany(targetEntity: Service::class)]
    #[ORM\JoinTable(name: 'favorite_list_service')]
    private Collection $services;

    /**
     * @var Collection<int, Destination>
     */
    #[ORM\ManyToMany(targetEntity: Destination::class)]
    #[ORM\JoinTable(name: 'favorite_list_destination')]
    private Collection $destinations;

    public function __construct()
    {
        $this->services = new ArrayCollection();
        $this->destinations = new ArrayCollection();
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, Service>
     */
    public function getServices(): Collection
    {
        return $this->services;
    }

    public function addService(Service $service): static
    {
        if (!$this->services->contains($service)) {
            $this->services->add($service);
        }

        return $this;
    }

    public function removeService(Service $service): static
    {
        $this->services->removeElement($service);

        return $this;
    }

    /**
     * @return Collection<int, Destination>
     */
    public function getDestinations(): Collection
    {
        return $this->destinations;
    }

    public function addDestination(Destination $destination): static
    {
        if (!$this->destinations->contains($destination)) {
            $this->destinations->add($destination);
        }

        return $this;
    }

    public function removeDestination(Destination $destination): static
    {
        $this->destinations->removeElement($destination);

        return $this;
    }
}
