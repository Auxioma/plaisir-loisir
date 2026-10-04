<?php

declare(strict_types=1);

namespace App\User\Entity;

use App\Shared\Doctrine\SoftDeletableTrait;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\Shared\Validator\ValidPhoneNumber;
use App\User\Enum\UserStatus;
use App\User\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use UlidIdentifierTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    /**
     * Rôles applicatifs (ROLE_USER est toujours ajouté implicitement).
     *
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * Mot de passe haché (jamais en clair).
     */
    #[ORM\Column]
    private string $password;

    #[ORM\Column(length: 100)]
    private string $firstName;

    #[ORM\Column(length: 100)]
    private string $lastName;

    #[ORM\Column(length: 30, nullable: true)]
    #[ValidPhoneNumber]
    private ?string $phone = null;

    #[ORM\Column(enumType: UserStatus::class)]
    private UserStatus $status = UserStatus::Pending;

    /*
     * ------------------------------------------------------------------------
     *  Réinitialisation du mot de passe (parcours en 3 écrans de la maquette).
     *
     *  Le code envoyé par e-mail n'est JAMAIS stocké en clair : seule son
     *  empreinte l'est, comme pour un mot de passe. Si la base fuite, les codes
     *  en cours ne sont pas exploitables.
     *
     *  Ces trois colonnes sont portées par l'utilisateur, et non par la session,
     *  pour que la limite de tentatives soit réellement contraignante : sinon il
     *  suffirait de vider ses cookies pour rejouer le compteur.
     * ------------------------------------------------------------------------
     */

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $resetCodeHash = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $resetCodeExpiresAt = null;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $resetCodeAttempts = 0;

    /*
     * ------------------------------------------------------------------------
     *  Vérification de l'adresse e-mail à l'inscription (Lot I, 15/09).
     *
     *  Même patron que la réinitialisation de mot de passe ci-dessus (code
     *  haché, jamais en clair ; compteur de tentatives porté par l'entité).
     *  Le compte reste UserStatus::Pending tant que le code n'est pas validé
     *  (RegistrationService) ; AccountChecker refuse la connexion jusque-là.
     *  Une inscription par connexion sociale saute cette étape : l'adresse
     *  est déjà attestée par le fournisseur (SocialLoginService).
     * ------------------------------------------------------------------------
     */

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emailVerificationCodeHash = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $emailVerificationExpiresAt = null;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $emailVerificationAttempts = 0;

    /**
     * Chemin de la photo de profil, relatif à `public/` (ex.
     * `uploads/avatars/xxx.jpg`) — null tant qu'aucune n'a été déposée.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatarPath = null;

    /**
     * Profil complémentaire (écran « Informations personnelles », maquette
     * profil_infos_particulier du 30/09) : tous facultatifs.
     */
    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $birthDate = null;

    /** 'femme', 'homme', 'autre' — null : non précisé. */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $gender = null;

    /** Langue préférée des communications ('fr', 'en'). */
    #[ORM\Column(length: 5, nullable: true)]
    private ?string $preferredLocale = null;

    /** Pays de résidence, code ISO 3166-1 alpha-2. */
    #[ORM\Column(length: 2, nullable: true)]
    private ?string $country = null;

    /**
     * @var Collection<int, Address>
     */
    #[ORM\OneToMany(targetEntity: Address::class, mappedBy: 'user', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $addresses;

    public function __construct()
    {
        $this->addresses = new ArrayCollection();
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Identifiant unique de sécurité : ici, l'email.
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getStatus(): UserStatus
    {
        return $this->status;
    }

    public function setStatus(UserStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getResetCodeHash(): ?string
    {
        return $this->resetCodeHash;
    }

    public function getResetCodeExpiresAt(): ?\DateTimeImmutable
    {
        return $this->resetCodeExpiresAt;
    }

    public function getResetCodeAttempts(): int
    {
        return $this->resetCodeAttempts;
    }

    /**
     * Arme un nouveau code de réinitialisation (empreinte + échéance) et
     * remet le compteur de tentatives à zéro.
     */
    public function startPasswordReset(string $codeHash, \DateTimeImmutable $expiresAt): static
    {
        $this->resetCodeHash = $codeHash;
        $this->resetCodeExpiresAt = $expiresAt;
        $this->resetCodeAttempts = 0;

        return $this;
    }

    public function registerFailedResetAttempt(): static
    {
        ++$this->resetCodeAttempts;

        return $this;
    }

    /**
     * Efface le code en cours : après un mot de passe changé, après trop de
     * tentatives, ou quand une nouvelle demande remplace l'ancienne.
     */
    public function clearPasswordReset(): static
    {
        $this->resetCodeHash = null;
        $this->resetCodeExpiresAt = null;
        $this->resetCodeAttempts = 0;

        return $this;
    }

    public function getEmailVerificationCodeHash(): ?string
    {
        return $this->emailVerificationCodeHash;
    }

    public function getEmailVerificationExpiresAt(): ?\DateTimeImmutable
    {
        return $this->emailVerificationExpiresAt;
    }

    public function getEmailVerificationAttempts(): int
    {
        return $this->emailVerificationAttempts;
    }

    /**
     * Arme un nouveau code de vérification (empreinte + échéance) et remet
     * le compteur de tentatives à zéro.
     */
    public function startEmailVerification(string $codeHash, \DateTimeImmutable $expiresAt): static
    {
        $this->emailVerificationCodeHash = $codeHash;
        $this->emailVerificationExpiresAt = $expiresAt;
        $this->emailVerificationAttempts = 0;

        return $this;
    }

    public function registerFailedVerificationAttempt(): static
    {
        ++$this->emailVerificationAttempts;

        return $this;
    }

    /**
     * Efface le code en cours : après vérification réussie, après trop de
     * tentatives, ou quand une nouvelle demande remplace l'ancienne.
     */
    public function clearEmailVerification(): static
    {
        $this->emailVerificationCodeHash = null;
        $this->emailVerificationExpiresAt = null;
        $this->emailVerificationAttempts = 0;

        return $this;
    }

    /**
     * Confirme l'adresse : active le compte et efface le code, en une fois.
     */
    public function markEmailVerified(): static
    {
        $this->status = UserStatus::Active;
        $this->clearEmailVerification();

        return $this;
    }

    public function getAvatarPath(): ?string
    {
        return $this->avatarPath;
    }

    public function setAvatarPath(?string $avatarPath): static
    {
        $this->avatarPath = $avatarPath;

        return $this;
    }

    public function getBirthDate(): ?\DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function setBirthDate(?\DateTimeImmutable $birthDate): static
    {
        $this->birthDate = $birthDate;

        return $this;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(?string $gender): static
    {
        $this->gender = $gender;

        return $this;
    }

    public function getPreferredLocale(): ?string
    {
        return $this->preferredLocale;
    }

    public function setPreferredLocale(?string $preferredLocale): static
    {
        $this->preferredLocale = $preferredLocale;

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

    /**
     * Adresse principale : la première enregistrée, créée à la demande par
     * l'écran « Informations personnelles ».
     */
    public function getMainAddress(): ?Address
    {
        $first = $this->addresses->first();

        return false === $first ? null : $first;
    }

    /**
     * @return Collection<int, Address>
     */
    public function getAddresses(): Collection
    {
        return $this->addresses;
    }

    public function addAddress(Address $address): static
    {
        if (!$this->addresses->contains($address)) {
            $this->addresses->add($address);
            $address->setUser($this);
        }

        return $this;
    }

    public function removeAddress(Address $address): static
    {
        if ($this->addresses->removeElement($address)) {
            if ($address->getUser() === $this) {
                $address->setUser(null);
            }
        }

        return $this;
    }

    /**
     * Aucune donnée sensible temporaire n'est stockée : rien à effacer.
     */
    public function eraseCredentials(): void
    {
    }
}
