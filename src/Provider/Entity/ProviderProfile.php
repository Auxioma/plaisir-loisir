<?php

declare(strict_types=1);

namespace App\Provider\Entity;

use App\Provider\Enum\ProviderStatus;
use App\Provider\Repository\ProviderProfileRepository;
use App\Shared\Doctrine\SoftDeletableTrait;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Profil professionnel rattaché à un User qui devient prestataire.
 *
 * La relation vers User est unidirectionnelle (portée ici) afin que le domaine
 * User reste indépendant du domaine Provider.
 */
#[ORM\Entity(repositoryClass: ProviderProfileRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ProviderProfile
{
    use UlidIdentifierTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private ?User $user = null;

    #[ORM\Column(length: 120)]
    private string $displayName;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $companyName = null;

    #[ORM\Column(enumType: ProviderStatus::class)]
    private ProviderStatus $status = ProviderStatus::Draft;

    // --- Réseaux sociaux (maquette, nullables) ---

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $facebookUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $instagramUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $linkedinUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $websiteUrl = null;

    // Les informations fiscales et légales (forme juridique, SIRET, TVA, siège,
    // représentant légal, assurance) vivent dans App\Legal\Entity\CompanyIdentity,
    // reliée à ce profil. Trois colonnes « fiscal* » traînaient ici, que
    // personne ne lisait et qui ne suffisaient à aucun dossier réel.

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): static
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;

        return $this;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(?string $companyName): static
    {
        $this->companyName = $companyName;

        return $this;
    }

    public function getStatus(): ProviderStatus
    {
        return $this->status;
    }

    public function setStatus(ProviderStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getFacebookUrl(): ?string
    {
        return $this->facebookUrl;
    }

    public function setFacebookUrl(?string $facebookUrl): static
    {
        $this->facebookUrl = $facebookUrl;

        return $this;
    }

    public function getInstagramUrl(): ?string
    {
        return $this->instagramUrl;
    }

    public function setInstagramUrl(?string $instagramUrl): static
    {
        $this->instagramUrl = $instagramUrl;

        return $this;
    }

    public function getLinkedinUrl(): ?string
    {
        return $this->linkedinUrl;
    }

    public function setLinkedinUrl(?string $linkedinUrl): static
    {
        $this->linkedinUrl = $linkedinUrl;

        return $this;
    }

    public function getWebsiteUrl(): ?string
    {
        return $this->websiteUrl;
    }

    public function setWebsiteUrl(?string $websiteUrl): static
    {
        $this->websiteUrl = $websiteUrl;

        return $this;
    }

    /**
     * Pont pour Symfony Workflow : expose le statut sous forme de chaîne.
     */
    public function getMarking(): string
    {
        return $this->status->value;
    }

    /**
     * Pont pour Symfony Workflow : applique un marquage (place) sous forme de chaîne.
     *
     * @param array<string, mixed> $context
     */
    public function setMarking(string $marking, array $context = []): void
    {
        $this->status = ProviderStatus::from($marking);
    }
}
