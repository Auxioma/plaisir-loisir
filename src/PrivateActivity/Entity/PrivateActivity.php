<?php

declare(strict_types=1);

namespace App\PrivateActivity\Entity;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\Service;
use App\Catalog\Enum\ActivityLevel;
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

    /**
     * Nullable depuis le 05/10 : un brouillon peut attendre la validation
     * d'une catégorie proposée (CategorySuggestion). Une activité publiée en
     * a toujours une (PrivateActivityDraftService::validateStep).
     */
    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
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

    /** Fin prévue (assistant de création, 05/10). */
    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $postalCode = null;

    /**
     * Coordonnées du lieu EXACT : soumises aux mêmes règles que `location`
     * (carte affichée seulement à qui peut voir l'adresse exacte).
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $longitude = null;

    /** Précisions de rendez-vous (mêmes règles de visibilité que `location`). */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $meetingPoint = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $toBring = null;

    /** Photo de couverture (chemin public, uploads/private-activities). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverImage = null;

    #[ORM\Column(enumType: PrivateActivityVisibility::class)]
    private PrivateActivityVisibility $visibility = PrivateActivityVisibility::Public;

    #[ORM\Column(enumType: ParticipationMode::class)]
    private ParticipationMode $participationMode = ParticipationMode::Validation;

    #[ORM\Column(enumType: PrivateActivityStatus::class)]
    private PrivateActivityStatus $status = PrivateActivityStatus::Open;

    /** Niveau attendu (retours client du 07/10), affiché sur les annonces. Nullable : anciennes activités. */
    #[ORM\Column(enumType: ActivityLevel::class, nullable: true)]
    private ?ActivityLevel $level = null;

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

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(?\DateTimeImmutable $endsAt): static
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getMeetingPoint(): ?string
    {
        return $this->meetingPoint;
    }

    public function setMeetingPoint(?string $meetingPoint): static
    {
        $this->meetingPoint = $meetingPoint;

        return $this;
    }

    public function getToBring(): ?string
    {
        return $this->toBring;
    }

    public function setToBring(?string $toBring): static
    {
        $this->toBring = $toBring;

        return $this;
    }

    public function getCoverImage(): ?string
    {
        return $this->coverImage;
    }

    public function setCoverImage(?string $coverImage): static
    {
        $this->coverImage = $coverImage;

        return $this;
    }

    public function getLevel(): ?ActivityLevel
    {
        return $this->level;
    }

    public function setLevel(?ActivityLevel $level): static
    {
        $this->level = $level;

        return $this;
    }

    /** Places confirmées (participations acceptées). */
    public function countAccepted(): int
    {
        return $this->participations->filter(static fn (Participation $p): bool => $p->isAccepted())->count();
    }

    /** Places restantes, null si la capacité n'est pas limitée. */
    public function remainingPlaces(): ?int
    {
        return null === $this->maxParticipants ? null : max(0, $this->maxParticipants - $this->countAccepted());
    }
}
