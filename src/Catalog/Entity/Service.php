<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use App\Catalog\Enum\BookingType;
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

    public function __construct()
    {
        $this->packages = new ArrayCollection();
        $this->media = new ArrayCollection();
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
}
