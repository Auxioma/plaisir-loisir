<?php

declare(strict_types=1);

namespace App\Corporate\Entity;

use App\Corporate\Repository\PartnerApplicationRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Candidature envoyée depuis « Devenir partenaire ».
 *
 * Les champs sont exactement ceux du formulaire de la maquette. Ils ne sont
 * volontairement PAS transformés en prestataire : une candidature se lit, se
 * qualifie et s'accepte. Créer un ProviderProfile dès l'envoi reviendrait à
 * laisser n'importe qui s'inscrire au catalogue sans le moindre contrôle.
 *
 * Comme pour les messages de contact, la candidature est enregistrée en base
 * et non simplement expédiée par e-mail : une file d'envoi arrêtée ne doit pas
 * faire disparaître un prospect.
 */
#[ORM\Entity(repositoryClass: PartnerApplicationRepository::class)]
#[ORM\Table(name: 'partner_application')]
#[ORM\Index(name: 'idx_partner_application_handled', columns: ['handled_at', 'created_at'])]
#[ORM\HasLifecycleCallbacks]
class PartnerApplication
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Veuillez indiquer le nom de votre site.')]
    private string $siteName = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Veuillez indiquer l'adresse de votre site.")]
    private string $siteUrl = '';

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: "Veuillez choisir votre secteur d'activité.")]
    private string $sector = '';

    /** Tranche de trafic déclarée, telle que la liste de la maquette la propose. */
    #[ORM\Column(length: 60)]
    #[Assert\NotBlank(message: 'Veuillez indiquer votre trafic mensuel.')]
    private string $traffic = '';

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $companyName = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $contactName = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Veuillez saisir votre adresse.')]
    private string $address = '';

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Veuillez saisir votre code postal.')]
    private string $postalCode = '';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Veuillez saisir votre adresse e-mail.')]
    #[Assert\Email(message: 'Veuillez saisir une adresse e-mail valide.')]
    private string $email = '';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 150, maxMessage: 'La description ne doit pas dépasser {{ limit }} caractères.')]
    private ?string $description = null;

    /**
     * Acceptation des conditions, cochée dans le formulaire.
     *
     * Conservée sur la candidature elle-même : contrairement à l'inscription,
     * il n'y a pas de compte auquel rattacher une preuve dans `legal_acceptance`.
     */
    #[ORM\Column(options: ['default' => false])]
    #[Assert\IsTrue(message: "Vous devez accepter les conditions d'utilisation.")]
    private bool $termsAccepted = false;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $handledAt = null;

    public function getSiteName(): string
    {
        return $this->siteName;
    }

    public function setSiteName(string $siteName): static
    {
        $this->siteName = trim($siteName);

        return $this;
    }

    public function getSiteUrl(): string
    {
        return $this->siteUrl;
    }

    public function setSiteUrl(string $siteUrl): static
    {
        $this->siteUrl = trim($siteUrl);

        return $this;
    }

    public function getSector(): string
    {
        return $this->sector;
    }

    public function setSector(string $sector): static
    {
        $this->sector = trim($sector);

        return $this;
    }

    public function getTraffic(): string
    {
        return $this->traffic;
    }

    public function setTraffic(string $traffic): static
    {
        $this->traffic = trim($traffic);

        return $this;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(?string $companyName): static
    {
        $this->companyName = self::nullIfEmpty($companyName);

        return $this;
    }

    public function getContactName(): ?string
    {
        return $this->contactName;
    }

    public function setContactName(?string $contactName): static
    {
        $this->contactName = self::nullIfEmpty($contactName);

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = self::nullIfEmpty($phone);

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = self::nullIfEmpty($city);

        return $this;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = trim($address);

        return $this;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): static
    {
        $this->postalCode = trim($postalCode);

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = self::nullIfEmpty($description);

        return $this;
    }

    public function isTermsAccepted(): bool
    {
        return $this->termsAccepted;
    }

    public function setTermsAccepted(bool $termsAccepted): static
    {
        $this->termsAccepted = $termsAccepted;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getHandledAt(): ?\DateTimeImmutable
    {
        return $this->handledAt;
    }

    public function markHandled(): static
    {
        $this->handledAt = new \DateTimeImmutable();

        return $this;
    }

    private static function nullIfEmpty(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmed = trim($value);

        return '' !== $trimmed ? $trimmed : null;
    }
}
