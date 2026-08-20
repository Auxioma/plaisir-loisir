<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use App\Catalog\Repository\ServiceDetailRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Contenu éditorial de la fiche détaillée d'une activité.
 *
 * POURQUOI UNE TABLE À PART
 * `Service` porte ce qui se cherche, se trie et se réserve : titre, prix,
 * durée, position, statut. La fiche détaillée, elle, est une page de vente :
 * une présentation, quatre listes à puces, des points de rendez-vous, des
 * garanties. Une quinzaine de champs qui ne servent qu'à un seul écran et ne
 * sont jamais interrogés. Les entasser sur `Service` aurait alourdi chaque
 * requête de listing pour rien — même raisonnement que CompanyIdentity face à
 * ProviderProfile.
 *
 * LES LISTES SONT EN JSON, et c'est délibéré : « ce qui est inclus » est une
 * suite de phrases ordonnées, sans identité propre, jamais recherchée ni
 * partagée. Une table par liste aurait produit cinq tables jointes à chaque
 * affichage pour n'y gagner aucune capacité réelle.
 *
 * AVERTISSEMENT SUR LES DONNÉES
 * La maquette ne fournit qu'UNE SEULE fiche détaillée, celle de la descente en
 * canoë, et le code statique l'affichait pour toutes les activités : ouvrir
 * « Visite du Musée » montrait « Descente en Canoë ». Le contenu réel des sept
 * autres fiches reste à fournir par le client.
 */
#[ORM\Entity(repositoryClass: ServiceDetailRepository::class)]
#[ORM\Table(name: 'service_detail')]
#[ORM\HasLifecycleCallbacks]
class ServiceDetail
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\OneToOne(inversedBy: 'detail')]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?Service $service = null;

    /**
     * Fil d'Ariane, tel que la maquette l'écrit.
     *
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    private array $breadcrumb = [];

    /** Nom de l'organisateur affiché sous le titre. */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $organizer = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $presentationSubtitle = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $presentationText = null;

    /** Phrase d'introduction des puces : « Cette descente vous permet de : ». */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $highlightsTitle = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $highlights = [];

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $included = [];

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $excluded = [];

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $cannotParticipate = [];

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $toBring = [];

    /**
     * Faits marquants du bandeau : durée, capacité, âge, type, avis.
     *
     * Stockés tels quels plutôt que reconstruits à partir de `Service`, parce
     * que la maquette y écrit des variantes qui ne correspondent pas aux
     * données de l'activité : « Sport & Aventure » au singulier là où la
     * catégorie est « Sports & Aventures », et « 15 avis » alors que l'en-tête
     * de la même page en annonce 256. Les dériver changerait le rendu.
     *
     * @var list<array{label: string, value: string, star?: bool}>
     */
    #[ORM\Column(type: 'json')]
    private array $keyFacts = [];

    /** Plan du point de départ. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mapImage = null;

    /**
     * Lignes du bloc « rendez-vous ».
     *
     * @var list<array{label: string, value: string}>
     */
    #[ORM\Column(type: 'json')]
    private array $meetingPoints = [];

    /**
     * Réassurances affichées sous le plan.
     *
     * La première dépend de l'activité (sa politique d'annulation), les trois
     * autres sont des promesses de la plateforme. Elles sont portées ici pour
     * que la fiche reste lisible d'un bloc.
     *
     * @var list<array{title: string, text: string}>
     */
    #[ORM\Column(type: 'json')]
    private array $guarantees = [];

    /**
     * Prix affiché dans le bloc de réservation.
     *
     * Distinct du prix de la carte : la maquette annonce 25 € sur la vignette
     * et 29 € sur la fiche. Incohérence relevée, reproduite en attendant
     * l'arbitrage.
     */
    #[ORM\Column(nullable: true)]
    private ?int $price = null;

    /**
     * Encart de synthèse des avis.
     *
     * Là encore, les chiffres de la maquette (4,5 sur 5, 8 955 avis) ne sont
     * pas ceux de l'en-tête (4.8, 256 avis) : trois comptages différents
     * cohabitent sur le même écran.
     */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $reviewsScore = null;

    #[ORM\Column(nullable: true)]
    private ?int $reviewsOutOf = null;

    #[ORM\Column(nullable: true)]
    private ?int $reviewsTotal = null;

    /** Titre de la modale de réservation. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $modalTitle = null;

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getBreadcrumb(): array
    {
        return $this->breadcrumb;
    }

    /**
     * @param list<string> $breadcrumb
     */
    public function setBreadcrumb(array $breadcrumb): static
    {
        $this->breadcrumb = $breadcrumb;

        return $this;
    }

    public function getOrganizer(): ?string
    {
        return $this->organizer;
    }

    public function setOrganizer(?string $organizer): static
    {
        $this->organizer = $organizer;

        return $this;
    }

    public function getPresentationSubtitle(): ?string
    {
        return $this->presentationSubtitle;
    }

    public function setPresentationSubtitle(?string $subtitle): static
    {
        $this->presentationSubtitle = $subtitle;

        return $this;
    }

    public function getPresentationText(): ?string
    {
        return $this->presentationText;
    }

    public function setPresentationText(?string $text): static
    {
        $this->presentationText = $text;

        return $this;
    }

    public function getHighlightsTitle(): ?string
    {
        return $this->highlightsTitle;
    }

    public function setHighlightsTitle(?string $title): static
    {
        $this->highlightsTitle = $title;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getHighlights(): array
    {
        return $this->highlights;
    }

    /**
     * @param list<string> $highlights
     */
    public function setHighlights(array $highlights): static
    {
        $this->highlights = $highlights;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getIncluded(): array
    {
        return $this->included;
    }

    /**
     * @param list<string> $included
     */
    public function setIncluded(array $included): static
    {
        $this->included = $included;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getExcluded(): array
    {
        return $this->excluded;
    }

    /**
     * @param list<string> $excluded
     */
    public function setExcluded(array $excluded): static
    {
        $this->excluded = $excluded;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getCannotParticipate(): array
    {
        return $this->cannotParticipate;
    }

    /**
     * @param list<string> $cannotParticipate
     */
    public function setCannotParticipate(array $cannotParticipate): static
    {
        $this->cannotParticipate = $cannotParticipate;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getToBring(): array
    {
        return $this->toBring;
    }

    /**
     * @param list<string> $toBring
     */
    public function setToBring(array $toBring): static
    {
        $this->toBring = $toBring;

        return $this;
    }

    /**
     * @return list<array{label: string, value: string, star?: bool}>
     */
    public function getKeyFacts(): array
    {
        return $this->keyFacts;
    }

    /**
     * @param list<array{label: string, value: string, star?: bool}> $keyFacts
     */
    public function setKeyFacts(array $keyFacts): static
    {
        $this->keyFacts = $keyFacts;

        return $this;
    }

    public function getMapImage(): ?string
    {
        return $this->mapImage;
    }

    public function setMapImage(?string $mapImage): static
    {
        $this->mapImage = $mapImage;

        return $this;
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public function getMeetingPoints(): array
    {
        return $this->meetingPoints;
    }

    /**
     * @param list<array{label: string, value: string}> $meetingPoints
     */
    public function setMeetingPoints(array $meetingPoints): static
    {
        $this->meetingPoints = $meetingPoints;

        return $this;
    }

    /**
     * @return list<array{title: string, text: string}>
     */
    public function getGuarantees(): array
    {
        return $this->guarantees;
    }

    /**
     * @param list<array{title: string, text: string}> $guarantees
     */
    public function setGuarantees(array $guarantees): static
    {
        $this->guarantees = $guarantees;

        return $this;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(?int $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getReviewsScore(): ?string
    {
        return $this->reviewsScore;
    }

    public function getReviewsOutOf(): ?int
    {
        return $this->reviewsOutOf;
    }

    public function getReviewsTotal(): ?int
    {
        return $this->reviewsTotal;
    }

    public function setReviewsSummary(?string $score, ?int $outOf, ?int $total): static
    {
        $this->reviewsScore = $score;
        $this->reviewsOutOf = $outOf;
        $this->reviewsTotal = $total;

        return $this;
    }

    public function getModalTitle(): ?string
    {
        return $this->modalTitle;
    }

    public function setModalTitle(?string $modalTitle): static
    {
        $this->modalTitle = $modalTitle;

        return $this;
    }
}
