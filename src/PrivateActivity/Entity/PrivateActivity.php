<?php

declare(strict_types=1);

namespace App\PrivateActivity\Entity;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\Service;
use App\PrivateActivity\Enum\ParticipationMode;
use App\PrivateActivity\Enum\PrivateActivityStatus;
use App\PrivateActivity\Enum\PrivateActivityVisibility;
use App\PrivateActivity\Repository\PrivateActivityRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Activité privée (sortie) organisée par un membre pour trouver d'autres
 * particuliers avec qui la partager (§12-13 du CDC).
 *
 * Deux façons d'y participer, tenues par deux entités distinctes : un membre
 * qui la découvre (publique ou réservée aux membres) et demande à y
 * participer (Participation, avec capacité et liste d'attente) ; ou
 * l'organisateur qui va chercher un membre précis (Invitation, sans notion de
 * capacité — une invitation directe n'est jamais mise en liste d'attente).
 */
#[ORM\Entity(repositoryClass: PrivateActivityRepository::class)]
#[ORM\HasLifecycleCallbacks]
class PrivateActivity
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $organizer = null;

    #[ORM\Column(length: 150)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Category $category = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $scheduledAt = null;

    /**
     * Zone approximative, TOUJOURS visible (§12.4 du CDC) : c'est elle que
     * voit un visiteur ou un participant pas encore accepté.
     */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $city = null;

    /**
     * Lieu de rendez-vous exact. Visible seulement si `showExactAddress` est
     * vrai, et seulement à l'organisateur et aux participants ACCEPTÉS —
     * jamais publiquement, jamais aux demandes en attente ou en liste
     * d'attente (voir PrivateActivityVoter::VIEW_EXACT_LOCATION).
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    /**
     * Certains organisateurs préfèrent ne jamais communiquer le lieu exact
     * via la plateforme (ils le donnent autrement). Faux : `location` reste
     * réservé à l'organisateur, même pour un participant accepté.
     */
    #[ORM\Column(options: ['default' => true])]
    private bool $showExactAddress = true;

    #[ORM\Column(enumType: PrivateActivityVisibility::class)]
    private PrivateActivityVisibility $visibility = PrivateActivityVisibility::Public;

    #[ORM\Column(enumType: ParticipationMode::class)]
    private ParticipationMode $participationMode = ParticipationMode::Validation;

    #[ORM\Column(enumType: PrivateActivityStatus::class)]
    private PrivateActivityStatus $status = PrivateActivityStatus::Open;

    /** Nullable : le CDC ne rend pas le minimum obligatoire (§12.2). */
    #[ORM\Column(nullable: true)]
    private ?int $minParticipants = null;

    /** Nullable = pas de limite. */
    #[ORM\Column(nullable: true)]
    private ?int $maxParticipants = null;

    /**
     * Lien optionnel vers une activité du catalogue dont s'inspire la sortie.
     */
    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Service $service = null;

    /**
     * @var Collection<int, Invitation>
     */
    #[ORM\OneToMany(targetEntity: Invitation::class, mappedBy: 'privateActivity', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $invitations;

    /**
     * @var Collection<int, Participation>
     */
    #[ORM\OneToMany(targetEntity: Participation::class, mappedBy: 'privateActivity', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $participations;

    public function __construct()
    {
        $this->invitations = new ArrayCollection();
        $this->participations = new ArrayCollection();
    }

    public function getOrganizer(): ?User
    {
        return $this->organizer;
    }

    public function setOrganizer(?User $organizer): static
    {
        $this->organizer = $organizer;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getScheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?\DateTimeImmutable $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function showsExactAddress(): bool
    {
        return $this->showExactAddress;
    }

    public function setShowExactAddress(bool $showExactAddress): static
    {
        $this->showExactAddress = $showExactAddress;

        return $this;
    }

    public function getVisibility(): PrivateActivityVisibility
    {
        return $this->visibility;
    }

    public function setVisibility(PrivateActivityVisibility $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function getParticipationMode(): ParticipationMode
    {
        return $this->participationMode;
    }

    public function setParticipationMode(ParticipationMode $participationMode): static
    {
        $this->participationMode = $participationMode;

        return $this;
    }

    public function getStatus(): PrivateActivityStatus
    {
        return $this->status;
    }

    public function setStatus(PrivateActivityStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isOpen(): bool
    {
        return PrivateActivityStatus::Open === $this->status;
    }

    public function getMinParticipants(): ?int
    {
        return $this->minParticipants;
    }

    public function setMinParticipants(?int $minParticipants): static
    {
        $this->minParticipants = $minParticipants;

        return $this;
    }

    public function getMaxParticipants(): ?int
    {
        return $this->maxParticipants;
    }

    public function setMaxParticipants(?int $maxParticipants): static
    {
        $this->maxParticipants = $maxParticipants;

        return $this;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;

        return $this;
    }

    /**
     * @return Collection<int, Invitation>
     */
    public function getInvitations(): Collection
    {
        return $this->invitations;
    }

    public function addInvitation(Invitation $invitation): static
    {
        if (!$this->invitations->contains($invitation)) {
            $this->invitations->add($invitation);
            $invitation->setPrivateActivity($this);
        }

        return $this;
    }

    public function removeInvitation(Invitation $invitation): static
    {
        if ($this->invitations->removeElement($invitation) && $invitation->getPrivateActivity() === $this) {
            $invitation->setPrivateActivity(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Participation>
     */
    public function getParticipations(): Collection
    {
        return $this->participations;
    }

    public function addParticipation(Participation $participation): static
    {
        if (!$this->participations->contains($participation)) {
            $this->participations->add($participation);
            $participation->setPrivateActivity($this);
        }

        return $this;
    }
}
