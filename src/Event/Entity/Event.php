<?php

declare(strict_types=1);

namespace App\Event\Entity;

use App\Event\Repository\EventRepository;
use App\Shared\Doctrine\SoftDeletableTrait;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un événement proposé aux membres : sortie, match, atelier, repas.
 *
 * LES DATES SONT DE VRAIES DATES, pas les libellés « 15 Mai 2026 » et
 * « 9h00 - 16h00 » que montre la maquette. Ces libellés se recomposent au
 * moment de l'affichage, alors que l'inverse est impossible : sans date
 * exploitable, l'écran calendrier ne peut pas placer l'événement dans une
 * case, ni le site trier par date ou masquer ce qui est passé.
 *
 * Le lieu, en revanche, reste une chaîne libre (« Autrans, 38880 ») : c'est
 * ainsi que la maquette l'écrit, et le découper en ville et code postal
 * supposerait une saisie structurée que le formulaire de création ne demande
 * pas.
 */
#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: 'event')]
#[ORM\Index(name: 'idx_event_starts_at', columns: ['starts_at'])]
#[ORM\HasLifecycleCallbacks]
class Event
{
    use UlidIdentifierTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Column(length: 180)]
    private string $title;

    #[ORM\Column(length: 200, unique: true)]
    private string $slug;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?EventCategory $category = null;

    /**
     * Organisateur. Nullable pour l'instant : les événements de démonstration
     * viennent de la maquette et n'ont pas d'auteur ; ceux créés par
     * l'assistant en auront un.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $organizer = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $location = null;

    /**
     * Texte affiché sous « Détails » sur la fiche événement. L'assistant de
     * création demande déjà cette information (StaticEventWizard, étape
     * « Image et description ») ; le champ n'existait pas encore côté entité.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    /**
     * Nombre de participants affiché sur la carte.
     *
     * Recopié plutôt que compté, comme les notes des activités : la maquette
     * annonce des volumes, et compter à chaque carte coûterait une requête de
     * plus par vignette. La valeur sera recalculée quand les inscriptions
     * existeront.
     */
    #[ORM\Column(options: ['default' => 0])]
    private int $participantsCount = 0;

    /**
     * Événement privé : il n'apparaît que dans l'onglet « Événements privés ».
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $private = false;

    /** Rang d'affichage : l'ordre des cartes est fixé par la maquette. */
    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    // ---- Assistant de création (maquettes creation_evenements, 04/10) ----

    /** Statut : draft (brouillon), scheduled (publication programmée), published. */
    #[ORM\Column(length: 20, options: ['default' => 'published'])]
    private string $status = 'published';

    /** Date de publication programmée. */
    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $publishAt = null;

    /** Format (étape 1) : sortie, repas, atelier, rencontre, autre. */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $eventType = null;

    /** Visibilité : private (sur invitation), public, group. */
    #[ORM\Column(length: 20, options: ['default' => 'public'])]
    private string $visibility = 'public';

    /** Courte description (120 caractères). */
    #[ORM\Column(length: 160, nullable: true)]
    private ?string $shortDescription = null;

    /** precise, periode ou recurrent. */
    #[ORM\Column(length: 20, options: ['default' => 'precise'])]
    private string $dateMode = 'precise';

    /** weekly ou monthly (événement récurrent). */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $recurrence = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $allDay = false;

    #[ORM\Column(length: 64, options: ['default' => 'Europe/Paris'])]
    private string $timezone = 'Europe/Paris';

    /** Rappel avant l'événement : 1h, 3h, 24h, 48h, 1w. */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $reminder = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $showInCalendar = true;

    /** Heure d'arrivée suggérée (HH:MM). */
    #[ORM\Column(length: 5, nullable: true)]
    private ?string $arrivalTime = null;

    /** precise, approx, online ou tbd (à définir). */
    #[ORM\Column(length: 20, options: ['default' => 'precise'])]
    private string $locationType = 'precise';

    /** Adresse complète (lieu précis ou approximatif). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $longitude = null;

    /** Lien de l'événement en ligne. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $onlineUrl = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $accessInstructions = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $meetingPoint = null;

    /**
     * Galerie (chemins publics, 5 maximum).
     *
     * @var list<string>
     */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $gallery = [];

    #[ORM\Column(options: ['default' => true])]
    private bool $registrationRequired = true;

    /** Nombre maximum de participants (null = illimité). */
    #[ORM\Column(nullable: true)]
    private ?int $capacity = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $waitlist = false;

    #[ORM\Column(options: ['default' => true])]
    private bool $showParticipants = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $allowGuestInvites = true;

    #[ORM\Column(options: ['default' => false])]
    private bool $commentsEnabled = false;

    #[ORM\Column(options: ['default' => true])]
    private bool $emailUpdates = true;

    public function __construct()
    {
        $this->startsAt = new \DateTimeImmutable();
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getCategory(): ?EventCategory
    {
        return $this->category;
    }

    public function setCategory(?EventCategory $category): static
    {
        $this->category = $category;

        return $this;
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

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): static
    {
        $this->imagePath = $imagePath;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(\DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;

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

    public function getParticipantsCount(): int
    {
        return $this->participantsCount;
    }

    public function setParticipantsCount(int $participantsCount): static
    {
        $this->participantsCount = $participantsCount;

        return $this;
    }

    public function isPrivate(): bool
    {
        return $this->private;
    }

    public function setPrivate(bool $private): static
    {
        $this->private = $private;

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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPublishAt(): ?\DateTimeImmutable
    {
        return $this->publishAt;
    }

    public function setPublishAt(?\DateTimeImmutable $publishAt): static
    {
        $this->publishAt = $publishAt;

        return $this;
    }

    public function getEventType(): ?string
    {
        return $this->eventType;
    }

    public function setEventType(?string $eventType): static
    {
        $this->eventType = $eventType;

        return $this;
    }

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    public function setVisibility(string $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): static
    {
        $this->shortDescription = $shortDescription;

        return $this;
    }

    public function getDateMode(): string
    {
        return $this->dateMode;
    }

    public function setDateMode(string $dateMode): static
    {
        $this->dateMode = $dateMode;

        return $this;
    }

    public function getRecurrence(): ?string
    {
        return $this->recurrence;
    }

    public function setRecurrence(?string $recurrence): static
    {
        $this->recurrence = $recurrence;

        return $this;
    }

    public function isAllDay(): bool
    {
        return $this->allDay;
    }

    public function setAllDay(bool $allDay): static
    {
        $this->allDay = $allDay;

        return $this;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getReminder(): ?string
    {
        return $this->reminder;
    }

    public function setReminder(?string $reminder): static
    {
        $this->reminder = $reminder;

        return $this;
    }

    public function isShowInCalendar(): bool
    {
        return $this->showInCalendar;
    }

    public function setShowInCalendar(bool $showInCalendar): static
    {
        $this->showInCalendar = $showInCalendar;

        return $this;
    }

    public function getArrivalTime(): ?string
    {
        return $this->arrivalTime;
    }

    public function setArrivalTime(?string $arrivalTime): static
    {
        $this->arrivalTime = $arrivalTime;

        return $this;
    }

    public function getLocationType(): string
    {
        return $this->locationType;
    }

    public function setLocationType(string $locationType): static
    {
        $this->locationType = $locationType;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

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

    public function getOnlineUrl(): ?string
    {
        return $this->onlineUrl;
    }

    public function setOnlineUrl(?string $onlineUrl): static
    {
        $this->onlineUrl = $onlineUrl;

        return $this;
    }

    public function getAccessInstructions(): ?string
    {
        return $this->accessInstructions;
    }

    public function setAccessInstructions(?string $accessInstructions): static
    {
        $this->accessInstructions = $accessInstructions;

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

    /** @return list<string> */
    public function getGallery(): array
    {
        return $this->gallery;
    }

    /** @param list<string> $gallery */
    public function setGallery(array $gallery): static
    {
        $this->gallery = $gallery;

        return $this;
    }

    public function isRegistrationRequired(): bool
    {
        return $this->registrationRequired;
    }

    public function setRegistrationRequired(bool $registrationRequired): static
    {
        $this->registrationRequired = $registrationRequired;

        return $this;
    }

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function setCapacity(?int $capacity): static
    {
        $this->capacity = $capacity;

        return $this;
    }

    public function isWaitlist(): bool
    {
        return $this->waitlist;
    }

    public function setWaitlist(bool $waitlist): static
    {
        $this->waitlist = $waitlist;

        return $this;
    }

    public function isShowParticipants(): bool
    {
        return $this->showParticipants;
    }

    public function setShowParticipants(bool $showParticipants): static
    {
        $this->showParticipants = $showParticipants;

        return $this;
    }

    public function isAllowGuestInvites(): bool
    {
        return $this->allowGuestInvites;
    }

    public function setAllowGuestInvites(bool $allowGuestInvites): static
    {
        $this->allowGuestInvites = $allowGuestInvites;

        return $this;
    }

    public function isCommentsEnabled(): bool
    {
        return $this->commentsEnabled;
    }

    public function setCommentsEnabled(bool $commentsEnabled): static
    {
        $this->commentsEnabled = $commentsEnabled;

        return $this;
    }

    public function isEmailUpdates(): bool
    {
        return $this->emailUpdates;
    }

    public function setEmailUpdates(bool $emailUpdates): static
    {
        $this->emailUpdates = $emailUpdates;

        return $this;
    }

    public function isPublished(?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable();

        return 'published' === $this->status || ('scheduled' === $this->status && null !== $this->publishAt && $this->publishAt <= $now);
    }

    /** Places restantes (null = illimité). */
    public function getRemainingSeats(): ?int
    {
        return null === $this->capacity ? null : max(0, $this->capacity - $this->participantsCount);
    }
}
