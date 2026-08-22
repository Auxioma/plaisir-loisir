<?php

declare(strict_types=1);

namespace App\Event\Entity;

use App\Event\Repository\EventCategoryRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Catégorie d'un événement : le badge coloré posé sur la carte.
 *
 * À ne pas confondre avec les pastilles de navigation de la page Événements
 * (Canoë/Kayak, VTT/Vélo, Randonnée…), qui sont une AUTRE liste, purement
 * éditoriale, avec ses propres icônes. La maquette en propose deux, et elles
 * ne se recoupent pas.
 *
 * La couleur est stockée sous son nom (« blue », « orange »…) et non en
 * hexadécimal : c'est ainsi que les feuilles de style la nomment, et la charte
 * peut changer la teinte sans qu'on touche aux données.
 */
#[ORM\Entity(repositoryClass: EventCategoryRepository::class)]
#[ORM\Table(name: 'event_category')]
#[ORM\HasLifecycleCallbacks]
class EventCategory
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\Column(length: 80)]
    private string $name;

    #[ORM\Column(length: 100, unique: true)]
    private string $slug;

    /** Nom de la couleur du badge : blue, orange, green, violet, navy. */
    #[ORM\Column(length: 20)]
    private string $color = 'blue';

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}
