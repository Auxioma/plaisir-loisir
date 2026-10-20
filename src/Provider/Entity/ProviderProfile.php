<?php

declare(strict_types=1);

namespace App\Provider\Entity;

use App\Catalog\Entity\Category;
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

    /**
     * Identifiant public de l'URL (`/professionnels/{slug}`), unique.
     *
     * Nullable en base pour les dossiers créés avant ce champ : voir
     * `ProviderSlugService`, qui le génère à l'inscription et rattrape les
     * profils existants via `app:provider:backfill-slugs`.
     */
    #[ORM\Column(length: 160, unique: true, nullable: true)]
    private ?string $slug = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $companyName = null;

    /**
     * Ville d'exercice affichée publiquement, distincte de l'adresse du siège
     * social (`CompanyIdentity::registeredCity`, non publique). Sert à la
     * recherche de professionnels par ville (§5, §9 du CDC) : même colonne
     * texte, en attendant un vrai rayon géographique (pas de géocodage en
     * place aujourd'hui).
     */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $city = null;

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

    /**
     * Activité principale déclarée à l'inscription (« Choix de l'activité »).
     *
     * POURQUOI UNE RELATION VERS LE DOMAINE CATALOGUE
     * Catalog dépend déjà de Provider (une prestation appartient à un
     * prestataire) ; pointer d'ici vers Category rend la dépendance mutuelle,
     * ce qu'on évite partout ailleurs. On l'assume ici parce que Category
     * n'est pas « le catalogue » : c'est la NOMENCLATURE des activités, un
     * vocabulaire de référence que les deux domaines nomment déjà de la même
     * façon. Recopier son libellé dans une colonne texte aurait produit des
     * intitulés divergents entre la fiche prestataire et le catalogue, et
     * privé le back-office d'une liste déroulante fiable.
     *
     * Nullable : les dossiers ouverts avant cet écran n'en ont pas.
     */
    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Category $mainCategory = null;

    // ---- Espace pro (maquettes profil_professionnel, 02/10) ----

    /** Adresse affichée (Paramètres / Mon profil). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    /** Image de couverture de la fiche (chemin public sous uploads/). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverPath = null;

    /** Zone d'intervention (ex. « Alpes, Auvergne-Rhône-Alpes »). */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $interventionZone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $youtubeUrl = null;

    /** Fuseau horaire des créneaux. */
    #[ORM\Column(length: 64, options: ['default' => 'Europe/Paris'])]
    private string $timezone = 'Europe/Paris';

    /** Disponibilités résumées (ex. « Lun - Dim : 7h - 20h »). */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $availabilityLabel = null;

    /*
     * Moyens de paiement que le professionnel propose à SES clients (17/10,
     * demande du client) : chaque prestation est réglée directement au
     * professionnel, jamais à TrouveMoi. Ces champs ne servent qu'à indiquer
     * au client comment payer ; la plateforme n'encaisse rien.
     */

    /** Lien de paiement en ligne du professionnel (son Stripe, SumUp, PayPal.me…). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $paymentLinkUrl = null;

    /** Adresse PayPal du professionnel. */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $paymentPaypal = null;

    /** IBAN du professionnel, communiqué au client pour un virement. */
    #[ORM\Column(length: 34, nullable: true)]
    private ?string $paymentIban = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $paymentIbanHolder = null;

    /** Moyens acceptés sur place (ex. « carte bancaire, espèces, chèques vacances »). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $paymentOnSite = null;

    /** Précisions libres (acompte, délai de règlement…). */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $paymentInstructions = null;

    /*
     * Paiement en ligne (20/10, maquette parametre_configuration_paiement_
     * prestataire) : le client paie à la réservation, par carte sur le compte
     * Stripe du professionnel (Stripe Connect, compte Standard relié) ou sur
     * son compte PayPal (`paymentPaypal`). L'argent arrive directement chez
     * le professionnel : il ne passe jamais par le compte de TrouveMoi.
     * Les champs lien / IBAN / « sur place » ci-dessus ne sont plus proposés
     * à l'écran (données conservées).
     */

    #[ORM\Column(options: ['default' => false])]
    private bool $onlinePaymentEnabled = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $acceptsStripe = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $acceptsPaypal = false;

    /** Paiement sur place, en espèces. */
    #[ORM\Column(options: ['default' => false])]
    private bool $acceptsCash = false;

    /** Compte Stripe relié (« acct_… »), créé par Stripe Connect. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeAccountId = null;

    /** Stripe autorise les paiements sur ce compte (inscription terminée). */
    #[ORM\Column(options: ['default' => false])]
    private bool $stripeChargesEnabled = false;

    // Les informations fiscales et légales (forme juridique, SIRET, TVA, siège,
    // représentant légal, assurance) vivent dans App\Legal\Entity\CompanyIdentity,
    // reliée à ce profil. Trois colonnes « fiscal* » traînaient ici, que
    // personne ne lisait et qui ne suffisaient à aucun dossier réel.

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getCoverPath(): ?string
    {
        return $this->coverPath;
    }

    public function setCoverPath(?string $coverPath): static
    {
        $this->coverPath = $coverPath;

        return $this;
    }

    public function getInterventionZone(): ?string
    {
        return $this->interventionZone;
    }

    public function setInterventionZone(?string $interventionZone): static
    {
        $this->interventionZone = $interventionZone;

        return $this;
    }

    public function getYoutubeUrl(): ?string
    {
        return $this->youtubeUrl;
    }

    public function setYoutubeUrl(?string $youtubeUrl): static
    {
        $this->youtubeUrl = $youtubeUrl;

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

    public function getAvailabilityLabel(): ?string
    {
        return $this->availabilityLabel;
    }

    public function setAvailabilityLabel(?string $availabilityLabel): static
    {
        $this->availabilityLabel = $availabilityLabel;

        return $this;
    }

    public function getPaymentLinkUrl(): ?string
    {
        return $this->paymentLinkUrl;
    }

    public function setPaymentLinkUrl(?string $paymentLinkUrl): static
    {
        $this->paymentLinkUrl = $paymentLinkUrl;

        return $this;
    }

    public function getPaymentPaypal(): ?string
    {
        return $this->paymentPaypal;
    }

    public function setPaymentPaypal(?string $paymentPaypal): static
    {
        $this->paymentPaypal = $paymentPaypal;

        return $this;
    }

    public function getPaymentIban(): ?string
    {
        return $this->paymentIban;
    }

    public function setPaymentIban(?string $paymentIban): static
    {
        $this->paymentIban = $paymentIban;

        return $this;
    }

    public function getPaymentIbanHolder(): ?string
    {
        return $this->paymentIbanHolder;
    }

    public function setPaymentIbanHolder(?string $paymentIbanHolder): static
    {
        $this->paymentIbanHolder = $paymentIbanHolder;

        return $this;
    }

    public function getPaymentOnSite(): ?string
    {
        return $this->paymentOnSite;
    }

    public function setPaymentOnSite(?string $paymentOnSite): static
    {
        $this->paymentOnSite = $paymentOnSite;

        return $this;
    }

    public function getPaymentInstructions(): ?string
    {
        return $this->paymentInstructions;
    }

    public function setPaymentInstructions(?string $paymentInstructions): static
    {
        $this->paymentInstructions = $paymentInstructions;

        return $this;
    }

    public function isOnlinePaymentEnabled(): bool
    {
        return $this->onlinePaymentEnabled;
    }

    public function setOnlinePaymentEnabled(bool $onlinePaymentEnabled): static
    {
        $this->onlinePaymentEnabled = $onlinePaymentEnabled;

        return $this;
    }

    public function acceptsStripe(): bool
    {
        return $this->acceptsStripe;
    }

    public function setAcceptsStripe(bool $acceptsStripe): static
    {
        $this->acceptsStripe = $acceptsStripe;

        return $this;
    }

    public function acceptsPaypal(): bool
    {
        return $this->acceptsPaypal;
    }

    public function setAcceptsPaypal(bool $acceptsPaypal): static
    {
        $this->acceptsPaypal = $acceptsPaypal;

        return $this;
    }

    public function acceptsCash(): bool
    {
        return $this->acceptsCash;
    }

    public function setAcceptsCash(bool $acceptsCash): static
    {
        $this->acceptsCash = $acceptsCash;

        return $this;
    }

    public function getStripeAccountId(): ?string
    {
        return $this->stripeAccountId;
    }

    public function setStripeAccountId(?string $stripeAccountId): static
    {
        $this->stripeAccountId = $stripeAccountId;

        return $this;
    }

    public function isStripeChargesEnabled(): bool
    {
        return $this->stripeChargesEnabled;
    }

    public function setStripeChargesEnabled(bool $stripeChargesEnabled): static
    {
        $this->stripeChargesEnabled = $stripeChargesEnabled;

        return $this;
    }

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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

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
    public function getMainCategory(): ?Category
    {
        return $this->mainCategory;
    }

    public function setMainCategory(?Category $mainCategory): static
    {
        $this->mainCategory = $mainCategory;

        return $this;
    }

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
