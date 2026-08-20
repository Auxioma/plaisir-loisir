<?php

declare(strict_types=1);

namespace App\User\Entity;

use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Enum\SocialProvider;
use App\User\Repository\SocialIdentityRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Lien entre un compte de la plateforme et une identité chez Google, Facebook
 * ou Apple.
 *
 * Un compte peut en porter plusieurs : la même personne peut se connecter
 * tantôt par Google, tantôt par Apple. D'où une table dédiée plutôt que des
 * colonnes sur `user`.
 *
 * La clé d'unicité porte sur (provider, external_id) et NON sur l'e-mail. C'est
 * le point à ne pas se tromper : l'identifiant que renvoie le fournisseur est
 * stable et lui appartient, alors que l'e-mail peut changer, être supprimé, ou
 * — chez Apple — être une adresse relais différente pour chaque application.
 */
#[ORM\Entity(repositoryClass: SocialIdentityRepository::class)]
#[ORM\Table(name: 'social_identity')]
#[ORM\UniqueConstraint(name: 'uniq_social_identity_provider_external', columns: ['provider', 'external_id'])]
#[ORM\Index(name: 'idx_social_identity_user', columns: ['user_id'])]
#[ORM\HasLifecycleCallbacks]
class SocialIdentity
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 20, enumType: SocialProvider::class)]
    private SocialProvider $provider;

    /**
     * Identifiant du compte chez le fournisseur (« sub » pour Google et Apple,
     * « id » pour Facebook). 255 caractères : Apple renvoie des chaînes longues.
     */
    #[ORM\Column(length: 255)]
    private string $externalId;

    /**
     * E-mail transmis par le fournisseur au moment de la liaison.
     *
     * Conservé à titre informatif seulement : il ne sert jamais à retrouver le
     * compte, sans quoi un fournisseur qui laisserait déclarer n'importe quelle
     * adresse permettrait de prendre la main sur un compte existant.
     */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $externalEmail = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $displayName = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $avatarUrl = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getProvider(): SocialProvider
    {
        return $this->provider;
    }

    public function setProvider(SocialProvider $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): static
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getExternalEmail(): ?string
    {
        return $this->externalEmail;
    }

    public function setExternalEmail(?string $externalEmail): static
    {
        $this->externalEmail = null !== $externalEmail ? mb_strtolower($externalEmail) : null;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): static
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function setAvatarUrl(?string $avatarUrl): static
    {
        $this->avatarUrl = $avatarUrl;

        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function touchLogin(): static
    {
        $this->lastLoginAt = new \DateTimeImmutable();

        return $this;
    }
}
