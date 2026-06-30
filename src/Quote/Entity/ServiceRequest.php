<?php

declare(strict_types=1);

namespace App\Quote\Entity;

use App\Catalog\Entity\Category;
use App\Quote\Enum\ServiceRequestStatus;
use App\Quote\Repository\ServiceRequestRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Demande de devis publiée par un client : les annonceurs y répondent par des devis.
 */
#[ORM\Entity(repositoryClass: ServiceRequestRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ServiceRequest
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $client = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Category $category = null;

    #[ORM\Column(length: 180)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(enumType: ServiceRequestStatus::class)]
    private ServiceRequestStatus $status = ServiceRequestStatus::Open;

    /**
     * @var Collection<int, Quote>
     */
    #[ORM\OneToMany(targetEntity: Quote::class, mappedBy: 'serviceRequest', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $quotes;

    public function __construct()
    {
        $this->quotes = new ArrayCollection();
    }

    public function getClient(): ?User
    {
        return $this->client;
    }

    public function setClient(?User $client): static
    {
        $this->client = $client;

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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStatus(): ServiceRequestStatus
    {
        return $this->status;
    }

    public function isOpen(): bool
    {
        return ServiceRequestStatus::Open === $this->status;
    }

    public function close(): void
    {
        $this->status = ServiceRequestStatus::Closed;
    }

    /**
     * @return Collection<int, Quote>
     */
    public function getQuotes(): Collection
    {
        return $this->quotes;
    }

    public function addQuote(Quote $quote): static
    {
        if (!$this->quotes->contains($quote)) {
            $this->quotes->add($quote);
            $quote->setServiceRequest($this);
        }

        return $this;
    }

    public function removeQuote(Quote $quote): static
    {
        if ($this->quotes->removeElement($quote) && $quote->getServiceRequest() === $this) {
            $quote->setServiceRequest(null);
        }

        return $this;
    }
}
