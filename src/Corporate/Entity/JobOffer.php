<?php

declare(strict_types=1);

namespace App\Corporate\Entity;

use App\Corporate\Repository\JobOfferRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Offre d'emploi des pages Carrières (09/11), auparavant figée dans
 * StaticCorporate::jobs() / jobDetail().
 *
 * Les listes de la fiche (missions, poste, formation, expérience) sont
 * saisies une ligne par élément : plus simple à éditer dans le back-office
 * qu'une collection, et suffisant pour des puces.
 */
#[ORM\Entity(repositoryClass: JobOfferRepository::class)]
#[ORM\Table(name: 'job_offer')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity('slug')]
class JobOffer
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\Column(length: 160, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Minuscules, chiffres et tirets uniquement.')]
    private string $slug = '';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private string $title = '';

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private string $summary = '';

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private string $city = '';

    #[ORM\Column(length: 40)]
    private string $contract = 'CDI';

    #[ORM\Column(length: 60)]
    private string $workTime = 'Temps complet';

    /** Service, sert aussi de filtre sur la page des offres. */
    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    private string $department = '';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $manager = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $division = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $missions = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $roleDescription = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $education = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $experience = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $icon = 'monitor';

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $tone = 'blue';

    #[ORM\Column(options: ['default' => true])]
    private bool $published = true;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    public function __toString(): string
    {
        return $this->title;
    }

    /**
     * @return list<string>
     */
    public static function lines(?string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $text) ?: []), static fn (string $l): bool => '' !== $l));
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = (string) $slug;

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

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): static
    {
        $this->summary = (string) $summary;

        return $this;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = (string) $city;

        return $this;
    }

    public function getContract(): string
    {
        return $this->contract;
    }

    public function setContract(?string $contract): static
    {
        $this->contract = (string) $contract;

        return $this;
    }

    public function getWorkTime(): string
    {
        return $this->workTime;
    }

    public function setWorkTime(?string $workTime): static
    {
        $this->workTime = (string) $workTime;

        return $this;
    }

    public function getDepartment(): string
    {
        return $this->department;
    }

    public function setDepartment(?string $department): static
    {
        $this->department = (string) $department;

        return $this;
    }

    public function getManager(): ?string
    {
        return $this->manager;
    }

    public function setManager(?string $manager): static
    {
        $this->manager = $manager;

        return $this;
    }

    public function getDivision(): ?string
    {
        return $this->division;
    }

    public function setDivision(?string $division): static
    {
        $this->division = $division;

        return $this;
    }

    public function getMissions(): ?string
    {
        return $this->missions;
    }

    public function setMissions(?string $missions): static
    {
        $this->missions = $missions;

        return $this;
    }

    public function getRoleDescription(): ?string
    {
        return $this->roleDescription;
    }

    public function setRoleDescription(?string $roleDescription): static
    {
        $this->roleDescription = $roleDescription;

        return $this;
    }

    public function getEducation(): ?string
    {
        return $this->education;
    }

    public function setEducation(?string $education): static
    {
        $this->education = $education;

        return $this;
    }

    public function getExperience(): ?string
    {
        return $this->experience;
    }

    public function setExperience(?string $experience): static
    {
        $this->experience = $experience;

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
