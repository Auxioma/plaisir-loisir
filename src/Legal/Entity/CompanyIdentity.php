<?php

declare(strict_types=1);

namespace App\Legal\Entity;

use App\Legal\Enum\LegalForm;
use App\Legal\Repository\CompanyIdentityRepository;
use App\Provider\Entity\ProviderProfile;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Identité légale de l'entreprise d'un prestataire.
 *
 * Séparée de ProviderProfile à dessein : le profil est ce que le public voit
 * (nom d'enseigne, présentation, réseaux sociaux), l'identité légale est ce que
 * l'administration vérifie (SIRET, TVA, représentant légal, assurance). Deux
 * usages, deux cycles de vie, deux niveaux de confidentialité.
 *
 * Elle remplace les trois champs « fiscal* » qui traînaient sur
 * ProviderProfile, jamais lus par personne et bien trop pauvres pour un dossier
 * réel.
 *
 * Tout est nullable sauf la forme juridique : le dossier se remplit
 * progressivement dans l'espace professionnel, et le prestataire ne peut être
 * vérifié qu'une fois complet.
 */
#[ORM\Entity(repositoryClass: CompanyIdentityRepository::class)]
#[ORM\Table(name: 'company_identity')]
#[ORM\UniqueConstraint(name: 'uniq_company_identity_siret', columns: ['siret'])]
#[ORM\HasLifecycleCallbacks]
class CompanyIdentity
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?ProviderProfile $providerProfile = null;

    #[ORM\Column(length: 30, enumType: LegalForm::class)]
    private LegalForm $legalForm = LegalForm::MicroEnterprise;

    /** Raison sociale — la dénomination inscrite au registre. */
    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $legalName = null;

    /** Nom commercial, quand il diffère de la raison sociale. */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $tradeName = null;

    #[ORM\Column(length: 9, nullable: true)]
    #[Assert\Regex(pattern: '/^\d{9}$/', message: 'Le SIREN doit comporter 9 chiffres.')]
    private ?string $siren = null;

    #[ORM\Column(length: 14, nullable: true)]
    #[Assert\Regex(pattern: '/^\d{14}$/', message: 'Le SIRET doit comporter 14 chiffres.')]
    private ?string $siret = null;

    /** Numéro de TVA intracommunautaire (FR + clé + SIREN). */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $vatNumber = null;

    /**
     * Franchise en base de TVA (article 293 B du CGI) : le prestataire ne la
     * facture pas. Cas courant des micro-entreprises, et déterminant pour le
     * calcul des prix affichés.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $vatExempt = false;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $rcsCity = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $rcsNumber = null;

    /** Code d'activité principale (APE/NAF), au format « 9329Z ». */
    #[ORM\Column(length: 6, nullable: true)]
    private ?string $apeCode = null;

    /**
     * Capital social. Stocké en décimal et non en flottant : un montant ne
     * supporte pas l'arrondi binaire. Doctrine le manipule sous forme de chaîne.
     */
    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, nullable: true)]
    private ?string $shareCapital = null;

    // --- Adresse du siège social ---

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $registeredStreet = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $registeredComplement = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $registeredPostalCode = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $registeredCity = null;

    /** Code pays ISO 3166-1 alpha-2. */
    #[ORM\Column(length: 2, nullable: true)]
    private ?string $registeredCountry = 'FR';

    // --- Représentant légal ---

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $legalRepresentativeName = null;

    /** Qualité du représentant : gérant, président, trésorier… */
    #[ORM\Column(length: 80, nullable: true)]
    private ?string $legalRepresentativeRole = null;

    // --- Assurance responsabilité civile professionnelle ---
    // Exigée pour la plupart des activités de loisirs encadrées ; sans elle, la
    // plateforme ne peut pas laisser publier en connaissance de cause.

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $insurerName = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $insurancePolicyNumber = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $insuranceExpiresAt = null;

    /** Date à laquelle l'administration a contrôlé les pièces. */
    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $verifiedAt = null;

    public function getProviderProfile(): ?ProviderProfile
    {
        return $this->providerProfile;
    }

    public function setProviderProfile(?ProviderProfile $providerProfile): static
    {
        $this->providerProfile = $providerProfile;

        return $this;
    }

    public function getLegalForm(): LegalForm
    {
        return $this->legalForm;
    }

    public function setLegalForm(LegalForm $legalForm): static
    {
        $this->legalForm = $legalForm;

        return $this;
    }

    public function getLegalName(): ?string
    {
        return $this->legalName;
    }

    public function setLegalName(?string $legalName): static
    {
        $this->legalName = $legalName;

        return $this;
    }

    public function getTradeName(): ?string
    {
        return $this->tradeName;
    }

    public function setTradeName(?string $tradeName): static
    {
        $this->tradeName = $tradeName;

        return $this;
    }

    public function getSiren(): ?string
    {
        return $this->siren;
    }

    public function setSiren(?string $siren): static
    {
        $this->siren = self::onlyDigits($siren);

        return $this;
    }

    public function getSiret(): ?string
    {
        return $this->siret;
    }

    /**
     * Le SIRET est saisi avec des espaces neuf fois sur dix (« 123 456 789
     * 00012 ») : on les retire à l'entrée, sinon la contrainte de format
     * rejetterait une saisie pourtant correcte.
     *
     * Les neuf premiers chiffres du SIRET SONT le SIREN : on le déduit plutôt
     * que de le redemander.
     */
    public function setSiret(?string $siret): static
    {
        $this->siret = self::onlyDigits($siret);

        if (null !== $this->siret && 14 === \strlen($this->siret)) {
            $this->siren = substr($this->siret, 0, 9);
        }

        return $this;
    }

    public function getVatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function setVatNumber(?string $vatNumber): static
    {
        $this->vatNumber = null !== $vatNumber ? strtoupper(str_replace(' ', '', $vatNumber)) : null;

        return $this;
    }

    public function isVatExempt(): bool
    {
        return $this->vatExempt;
    }

    public function setVatExempt(bool $vatExempt): static
    {
        $this->vatExempt = $vatExempt;

        return $this;
    }

    public function getRcsCity(): ?string
    {
        return $this->rcsCity;
    }

    public function setRcsCity(?string $rcsCity): static
    {
        $this->rcsCity = $rcsCity;

        return $this;
    }

    public function getRcsNumber(): ?string
    {
        return $this->rcsNumber;
    }

    public function setRcsNumber(?string $rcsNumber): static
    {
        $this->rcsNumber = $rcsNumber;

        return $this;
    }

    public function getApeCode(): ?string
    {
        return $this->apeCode;
    }

    public function setApeCode(?string $apeCode): static
    {
        $this->apeCode = null !== $apeCode ? strtoupper(str_replace(['.', ' '], '', $apeCode)) : null;

        return $this;
    }

    public function getShareCapital(): ?string
    {
        return $this->shareCapital;
    }

    public function setShareCapital(?string $shareCapital): static
    {
        $this->shareCapital = $shareCapital;

        return $this;
    }

    public function getRegisteredStreet(): ?string
    {
        return $this->registeredStreet;
    }

    public function setRegisteredStreet(?string $registeredStreet): static
    {
        $this->registeredStreet = $registeredStreet;

        return $this;
    }

    public function getRegisteredComplement(): ?string
    {
        return $this->registeredComplement;
    }

    public function setRegisteredComplement(?string $registeredComplement): static
    {
        $this->registeredComplement = $registeredComplement;

        return $this;
    }

    public function getRegisteredPostalCode(): ?string
    {
        return $this->registeredPostalCode;
    }

    public function setRegisteredPostalCode(?string $registeredPostalCode): static
    {
        $this->registeredPostalCode = $registeredPostalCode;

        return $this;
    }

    public function getRegisteredCity(): ?string
    {
        return $this->registeredCity;
    }

    public function setRegisteredCity(?string $registeredCity): static
    {
        $this->registeredCity = $registeredCity;

        return $this;
    }

    public function getRegisteredCountry(): ?string
    {
        return $this->registeredCountry;
    }

    public function setRegisteredCountry(?string $registeredCountry): static
    {
        $this->registeredCountry = null !== $registeredCountry ? strtoupper($registeredCountry) : null;

        return $this;
    }

    public function getLegalRepresentativeName(): ?string
    {
        return $this->legalRepresentativeName;
    }

    public function setLegalRepresentativeName(?string $name): static
    {
        $this->legalRepresentativeName = $name;

        return $this;
    }

    public function getLegalRepresentativeRole(): ?string
    {
        return $this->legalRepresentativeRole;
    }

    public function setLegalRepresentativeRole(?string $role): static
    {
        $this->legalRepresentativeRole = $role;

        return $this;
    }

    public function getInsurerName(): ?string
    {
        return $this->insurerName;
    }

    public function setInsurerName(?string $insurerName): static
    {
        $this->insurerName = $insurerName;

        return $this;
    }

    public function getInsurancePolicyNumber(): ?string
    {
        return $this->insurancePolicyNumber;
    }

    public function setInsurancePolicyNumber(?string $number): static
    {
        $this->insurancePolicyNumber = $number;

        return $this;
    }

    public function getInsuranceExpiresAt(): ?\DateTimeImmutable
    {
        return $this->insuranceExpiresAt;
    }

    public function setInsuranceExpiresAt(?\DateTimeImmutable $insuranceExpiresAt): static
    {
        $this->insuranceExpiresAt = $insuranceExpiresAt;

        return $this;
    }

    public function isInsuranceValid(?\DateTimeImmutable $at = null): bool
    {
        return null !== $this->insuranceExpiresAt
            && $this->insuranceExpiresAt >= ($at ?? new \DateTimeImmutable());
    }

    public function getVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function markVerified(): static
    {
        $this->verifiedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Le dossier contient-il le minimum exigible avant vérification ?
     *
     * Une association n'a ni SIRET commercial ni capital : on ne réclame donc
     * pas les mêmes pièces à tout le monde.
     */
    public function isComplete(): bool
    {
        $common = null !== $this->legalName
            && null !== $this->registeredStreet
            && null !== $this->registeredPostalCode
            && null !== $this->registeredCity
            && null !== $this->legalRepresentativeName;

        if (!$common) {
            return false;
        }

        return LegalForm::Association === $this->legalForm
            || (null !== $this->siret && self::isValidSiret($this->siret));
    }

    /**
     * Contrôle de la clé de Luhn du SIRET.
     *
     * Un SIRET n'est pas qu'une suite de quatorze chiffres : la dernière est une
     * clé de contrôle. Vérifier ce calcul écarte les fautes de frappe et les
     * numéros inventés, sans le moindre appel réseau.
     *
     * Exception connue : les établissements de La Poste ne respectent pas
     * l'algorithme. Le cas est trop marginal ici pour être traité à part.
     */
    public static function isValidSiret(string $siret): bool
    {
        $digits = self::onlyDigits($siret) ?? '';

        if (14 !== \strlen($digits)) {
            return false;
        }

        $sum = 0;
        foreach (str_split($digits) as $position => $digit) {
            $value = (int) $digit;

            // Un chiffre sur deux est doublé ; la longueur étant paire, ce sont
            // ceux de rang pair en partant de la gauche.
            if (0 === $position % 2) {
                $value *= 2;
                if ($value > 9) {
                    $value -= 9;
                }
            }

            $sum += $value;
        }

        return 0 === $sum % 10;
    }

    private static function onlyDigits(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        return '' !== $digits ? $digits : null;
    }
}
