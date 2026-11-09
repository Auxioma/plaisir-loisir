<?php

declare(strict_types=1);

namespace App\Corporate\Entity;

use App\Corporate\Enum\PageBlockSection;
use App\Corporate\Repository\PageBlockRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Bloc de contenu des pages institutionnelles (09/11) : une valeur, un membre
 * de l'équipe, un témoignage, une étape… Il remplace StaticCorporate, dont le
 * contenu est chargé par la commande app:contenu:importer.
 *
 * Selon la section, « Titre » est un intitulé (valeurs, étapes) ou un nom de
 * personne (équipe), et « Texte » une description ou une citation.
 */
#[ORM\Entity(repositoryClass: PageBlockRepository::class)]
#[ORM\Table(name: 'page_block')]
#[ORM\Index(name: 'idx_page_block_section', columns: ['section', 'position'])]
#[ORM\HasLifecycleCallbacks]
class PageBlock
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\Column(length: 40, enumType: PageBlockSection::class)]
    private PageBlockSection $section = PageBlockSection::AboutValues;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private string $title = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $text = null;

    /** Pour les témoignages : nom affiché sous la citation. */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $author = null;

    /** Fonction (équipe) ou activité (témoignage). */
    #[ORM\Column(length: 160, nullable: true)]
    private ?string $role = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    /** Nom d'icône du jeu d'icônes du site (provider/space/_ico.html.twig). */
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $icon = null;

    /** Teinte de la pastille : blue, green, orange, red, violet, yellow… */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $tone = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $featured = false;

    #[ORM\Column(options: ['default' => true])]
    private bool $published = true;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    public function __toString(): string
    {
        return $this->title;
    }

    public function getSection(): PageBlockSection
    {
        return $this->section;
    }

    public function setSection(PageBlockSection $section): static
    {
        $this->section = $section;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = (string) $title;

        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(?string $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(?string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getTone(): ?string
    {
        return $this->tone;
    }

    public function setTone(?string $tone): static
    {
        $this->tone = $tone;

        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->featured;
    }

    public function setFeatured(bool $featured): static
    {
        $this->featured = $featured;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): static
    {
        $this->published = $published;

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
