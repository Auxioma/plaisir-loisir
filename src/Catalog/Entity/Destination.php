<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use App\Catalog\Repository\DestinationRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Lieu navigable (ville/région) pour explorer les activités par destination.
 */
#[ORM\Entity(repositoryClass: DestinationRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Destination
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 140, unique: true)]
    private string $slug;

    /**
     * Code pays ISO 3166-1 alpha-2 (ex. "FR", "SN").
     */
    #[ORM\Column(length: 2)]
    private string $country;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $region = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $heroImage = null;

    /*
     * ------------------------------------------------------------------------
     *  Champs d'AFFICHAGE de la carte de destination (câblage du lot 2).
     *  Même démarche que sur Service : la maquette montre des valeurs que
     *  l'entité ne savait pas produire.
     * ------------------------------------------------------------------------
     */

    /** Accroche sous le nom : « Ville lumière et capitale de la culture ». */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $tagline = null;

    /**
     * Note moyenne et nombre d'avis, recopiés depuis les avis — même
     * dénormalisation assumée que sur Service, pour la même raison : une
     * grille de seize cartes ne doit pas lancer seize agrégations.
     */
    #[ORM\Column(type: 'decimal', precision: 3, scale: 2, nullable: true)]
    private ?string $ratingAverage = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $reviewsCount = 0;

    /**
     * Nombre d'activités annoncé sur la carte (« 32 activités »).
     *
     * Recopié plutôt que compté : la maquette affiche des volumes commerciaux
     * qui ne correspondent pas au nombre d'activités réellement publiées, et
     * un COUNT par carte coûterait une requête de plus à chaque affichage.
     */
    #[ORM\Column(options: ['default' => 0])]
    private int $activitiesCount = 0;

    /** Prix d'appel affiché sur la carte (« à partir de 25 € »). */
    #[ORM\Column(nullable: true)]
    private ?int $priceFrom = null;

    /** Pastille : « Populaire », « Bestseller », « Tendance ». */
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $badge = null;

    /**
     * Nom, accroche et pays normalisés : minuscules, sans accents.
     * Même dispositif que sur Service, et pour la même raison.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $searchText = null;

    /** Rang d'affichage : l'ordre des seize cartes est fixé par la maquette. */
    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    public function getTagline(): ?string
    {
        return $this->tagline;
    }

    public function setTagline(?string $tagline): static
    {
        $this->tagline = $tagline;

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
     * Les deux valeurs vont toujours ensemble : une note sans son nombre
     * d'avis n'a aucun sens à l'écran.
     */
    public function setRatingSummary(?string $average, int $count): static
    {
        $this->ratingAverage = $average;
        $this->reviewsCount = $count;

        return $this;
    }

    public function getActivitiesCount(): int
    {
        return $this->activitiesCount;
    }

    public function setActivitiesCount(int $activitiesCount): static
    {
        $this->activitiesCount = $activitiesCount;

        return $this;
    }

    public function getPriceFrom(): ?int
    {
        return $this->priceFrom;
    }

    public function setPriceFrom(?int $priceFrom): static
    {
        $this->priceFrom = $priceFrom;

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

    public function getSearchText(): ?string
    {
        return $this->searchText;
    }

    #[ORM\PrePersist]
    #[ORM\PreFlush]
    public function refreshSearchIndex(): void
    {
        $this->searchText = self::normalizeForSearch(implode(' ', array_filter([
            $this->name,
            $this->tagline,
            $this->region,
        ])));
    }

    /**
     * Voir Service::normalizeForSearch : la même normalisation des deux côtés
     * de la comparaison est la seule façon de garantir qu'ils se rencontrent.
     */
    public static function normalizeForSearch(string $value): string
    {
        return Service::normalizeForSearch($value);
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(?string $region): static
    {
        $this->region = $region;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getHeroImage(): ?string
    {
        return $this->heroImage;
    }

    public function setHeroImage(?string $heroImage): static
    {
        $this->heroImage = $heroImage;

        return $this;
    }
}
