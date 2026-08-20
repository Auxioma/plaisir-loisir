<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use App\Catalog\Enum\ActivityLevel;
use App\Catalog\Enum\ActivityType;
use App\Catalog\Enum\BookingType;
use App\Catalog\Enum\CancellationPolicy;
use App\Catalog\Enum\OpeningPeriod;
use App\Catalog\Enum\ServiceStatus;
use App\Catalog\Repository\ServiceRepository;
use App\Provider\Entity\ProviderProfile;
use App\Shared\Doctrine\SoftDeletableTrait;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Prestation publiée par un prestataire et classée dans une catégorie.
 */
#[ORM\Entity(repositoryClass: ServiceRepository::class)]
#[ORM\Index(columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class Service
{
    use UlidIdentifierTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\ManyToOne(targetEntity: ProviderProfile::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?ProviderProfile $provider = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    #[ORM\Column(length: 180)]
    private string $title;

    #[ORM\Column(length: 200, unique: true)]
    private string $slug;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(enumType: BookingType::class)]
    private BookingType $bookingType = BookingType::ServiceProduct;

    #[ORM\Column(enumType: ServiceStatus::class)]
    private ServiceStatus $status = ServiceStatus::Draft;

    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    private string $currency = 'EUR';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $shortDescription = null;

    #[ORM\Column(nullable: true)]
    private ?int $durationMinutes = null;

    /*
     * ------------------------------------------------------------------------
     *  Champs d'AFFICHAGE de la carte d'activité (câblage du lot 2).
     *
     *  Ils existent parce que la maquette montre des valeurs qu'aucun champ
     *  existant ne pouvait produire — et non pour dupliquer des données.
     * ------------------------------------------------------------------------
     */

    /**
     * Libellé de lieu affiché sous le titre : « Gorges de L'Ardèche »,
     * « Muséum d'Histoire Naturelle ».
     *
     * Ce n'est ni la ville ni la destination : ce sont des lieux-dits, des
     * massifs ou des établissements. Les ranger dans `city` aurait rendu la
     * colonne inexploitable pour filtrer par ville.
     */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $placeLabel = null;

    /**
     * Durée telle qu'elle est écrite sur la carte : « 2h-3h », « Journée »,
     * « 1h30 ».
     *
     * `durationMinutes` reste la donnée exploitable (tri, filtres, créneaux) ;
     * mais aucun formatage ne produit « Journée » à partir d'un nombre de
     * minutes, et la maquette affiche aussi des fourchettes.
     */
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $durationLabel = null;

    /** Pastille de mise en avant : « Bestseller », « Nouveau »… */
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $badge = null;

    /**
     * Rang d'affichage dans les listes.
     *
     * La maquette fixe l'ordre des huit cartes du listing. Sans ce champ, il
     * faudrait s'en remettre à l'ordre d'insertion — que rien ne garantit — ou
     * trier par titre, ce qui bousculerait la grille validée.
     */
    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    /**
     * Note moyenne et nombre d'avis, RECOPIÉS ici depuis la table des avis.
     *
     * Dénormalisation assumée : une grille de douze cartes déclencherait
     * autrement douze agrégations sur `review` à chaque affichage. La valeur
     * est recalculée quand un avis est publié, modéré ou supprimé.
     */
    #[ORM\Column(type: 'decimal', precision: 3, scale: 2, nullable: true)]
    private ?string $ratingAverage = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $reviewsCount = 0;

    #[ORM\Column(nullable: true)]
    private ?int $capacity = null;

    #[ORM\Column(enumType: ActivityLevel::class, nullable: true)]
    private ?ActivityLevel $level = null;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    private array $languages = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $included = null;

    // --- Champs additionnels issus de la maquette TrouveMoi (beta, tous nullables) ---

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $subtitle = null;

    #[ORM\Column(enumType: ActivityType::class, nullable: true)]
    private ?ActivityType $activityType = null;

    #[ORM\Column(nullable: true)]
    private ?int $minimumAge = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $programme = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $meetingPoint = null;

    #[ORM\Column(enumType: OpeningPeriod::class, nullable: true)]
    private ?OpeningPeriod $openingPeriod = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $audience = null;

    #[ORM\Column(enumType: CancellationPolicy::class, options: ['default' => 'flexible'])]
    private CancellationPolicy $cancellationPolicy = CancellationPolicy::Flexible;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $longitude = null;

    #[ORM\ManyToOne(targetEntity: Destination::class)]
    private ?Destination $destination = null;

    /**
     * @var Collection<int, ServicePackage>
     */
    #[ORM\OneToMany(targetEntity: ServicePackage::class, mappedBy: 'service', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $packages;

    /**
     * @var Collection<int, Media>
     */
    #[ORM\OneToMany(targetEntity: Media::class, mappedBy: 'service', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $media;

    /**
     * @var Collection<int, ServiceOption>
     */
    #[ORM\OneToMany(targetEntity: ServiceOption::class, mappedBy: 'service', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $options;

    public function __construct()
    {
        $this->packages = new ArrayCollection();
        $this->media = new ArrayCollection();
        $this->options = new ArrayCollection();
    }

    public function getProvider(): ?ProviderProfile
    {
        return $this->provider;
    }

    public function setProvider(?ProviderProfile $provider): static
    {
        $this->provider = $provider;

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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getBookingType(): BookingType
    {
        return $this->bookingType;
    }

    public function setBookingType(BookingType $bookingType): static
    {
        $this->bookingType = $bookingType;

        return $this;
    }

    public function getStatus(): ServiceStatus
    {
        return $this->status;
    }

    public function setStatus(ServiceStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

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

    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(?int $durationMinutes): static
    {
        $this->durationMinutes = $durationMinutes;

        return $this;
    }

    public function getPlaceLabel(): ?string
    {
        return $this->placeLabel;
    }

    public function setPlaceLabel(?string $placeLabel): static
    {
        $this->placeLabel = $placeLabel;

        return $this;
    }

    public function getDurationLabel(): ?string
    {
        return $this->durationLabel;
    }

    public function setDurationLabel(?string $durationLabel): static
    {
        $this->durationLabel = $durationLabel;

        return $this;
    }

    public function getBadge(): ?string
    {
        return $this->badge;
    }

    public function setBadge(?string $badge): static
    {
        $this->badge = $badge;

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

    public function getRatingAverage(): ?string
    {
        return $this->ratingAverage;
    }

    public function getReviewsCount(): int
    {
        return $this->reviewsCount;
    }

    /**
     * Recopie les valeurs agrégées de la table des avis.
     *
     * Les deux vont toujours ensemble : une note sans son nombre d'avis n'a
     * aucun sens à l'écran, et les laisser diverger produirait « 4.8 (0 avis) ».
     */
    public function setRatingSummary(?string $average, int $count): static
    {
        $this->ratingAverage = $average;
        $this->reviewsCount = $count;

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

    public function getLevel(): ?ActivityLevel
    {
        return $this->level;
    }

    public function setLevel(?ActivityLevel $level): static
    {
        $this->level = $level;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getLanguages(): array
    {
        return $this->languages;
    }

    /**
     * @param list<string> $languages
     */
    public function setLanguages(array $languages): static
    {
        $this->languages = $languages;

        return $this;
    }

    public function getIncluded(): ?string
    {
        return $this->included;
    }

    public function setIncluded(?string $included): static
    {
        $this->included = $included;

        return $this;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): static
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    public function getActivityType(): ?ActivityType
    {
        return $this->activityType;
    }

    public function setActivityType(?ActivityType $activityType): static
    {
        $this->activityType = $activityType;

        return $this;
    }

    public function getMinimumAge(): ?int
    {
        return $this->minimumAge;
    }

    public function setMinimumAge(?int $minimumAge): static
    {
        $this->minimumAge = $minimumAge;

        return $this;
    }

    public function getProgramme(): ?string
    {
        return $this->programme;
    }

    public function setProgramme(?string $programme): static
    {
        $this->programme = $programme;

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

    public function getOpeningPeriod(): ?OpeningPeriod
    {
        return $this->openingPeriod;
    }

    public function setOpeningPeriod(?OpeningPeriod $openingPeriod): static
    {
        $this->openingPeriod = $openingPeriod;

        return $this;
    }

    public function getAudience(): ?string
    {
        return $this->audience;
    }

    public function setAudience(?string $audience): static
    {
        $this->audience = $audience;

        return $this;
    }

    public function getCancellationPolicy(): CancellationPolicy
    {
        return $this->cancellationPolicy;
    }

    public function setCancellationPolicy(CancellationPolicy $cancellationPolicy): static
    {
        $this->cancellationPolicy = $cancellationPolicy;

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

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;

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

    public function getDestination(): ?Destination
    {
        return $this->destination;
    }

    public function setDestination(?Destination $destination): static
    {
        $this->destination = $destination;

        return $this;
    }

    /**
     * @return Collection<int, ServicePackage>
     */
    public function getPackages(): Collection
    {
        return $this->packages;
    }

    public function addPackage(ServicePackage $package): static
    {
        if (!$this->packages->contains($package)) {
            $this->packages->add($package);
            $package->setService($this);
        }

        return $this;
    }

    public function removePackage(ServicePackage $package): static
    {
        if ($this->packages->removeElement($package) && $package->getService() === $this) {
            $package->setService(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Media>
     */
    public function getMedia(): Collection
    {
        return $this->media;
    }

    public function addMedia(Media $media): static
    {
        if (!$this->media->contains($media)) {
            $this->media->add($media);
            $media->setService($this);
        }

        return $this;
    }

    public function removeMedia(Media $media): static
    {
        if ($this->media->removeElement($media) && $media->getService() === $this) {
            $media->setService(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, ServiceOption>
     */
    public function getOptions(): Collection
    {
        return $this->options;
    }

    public function addOption(ServiceOption $option): static
    {
        if (!$this->options->contains($option)) {
            $this->options->add($option);
            $option->setService($this);
        }

        return $this;
    }

    public function removeOption(ServiceOption $option): static
    {
        if ($this->options->removeElement($option) && $option->getService() === $this) {
            $option->setService(null);
        }

        return $this;
    }
}
