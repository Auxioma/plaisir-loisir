<?php

declare(strict_types=1);

namespace App\Support\Entity;

use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\Support\Enum\FaqCategory;
use App\Support\Repository\FaqEntryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * UNE question fréquente et sa réponse.
 *
 * CE QUI DISTINGUE CETTE TABLE DE CELLE DES TEXTES JURIDIQUES
 * Un document juridique est versionné et ne se modifie jamais une fois publié,
 * parce qu'il faut pouvoir prouver ce qu'un utilisateur a accepté. Une réponse
 * de FAQ n'engage personne : elle se corrige sur place, et une correction ne
 * doit surtout pas obliger à publier une « version 2 » de la FAQ entière.
 * D'où deux tables et non une seule.
 *
 * LE COUPLE (langue, rubrique, position)
 * Chaque langue a ses propres lignes : la question française et sa traduction
 * anglaise sont deux enregistrements distincts, exactement comme pour les
 * textes juridiques. C'est plus simple à tenir qu'un champ par langue, et cela
 * permet d'avoir une FAQ anglaise plus courte sans laisser de trous.
 */
#[ORM\Entity(repositoryClass: FaqEntryRepository::class)]
#[ORM\Table(name: 'faq_entry')]
#[ORM\Index(name: 'idx_faq_entry_listing', columns: ['locale', 'category', 'position'])]
#[ORM\HasLifecycleCallbacks]
class FaqEntry
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\Column(length: 30, enumType: FaqCategory::class)]
    #[Assert\NotNull(message: 'Choisissez une rubrique : sans elle, la question n\'apparaîtrait sur aucune page.')]
    private FaqCategory $category = FaqCategory::Booking;

    #[ORM\Column(length: 5)]
    #[Assert\Length(max: 5)]
    private string $locale = 'fr';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'La question est le titre de l\'accordéon : elle ne peut pas être vide.')]
    #[Assert\Length(max: 255)]
    private string $question;

    /**
     * Texte riche, filtré à l'affichage par le même service que les textes
     * juridiques : une réponse contient souvent un lien vers les CGV ou vers
     * le formulaire de contact.
     */
    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Une question sans réponse est pire que pas de question du tout.')]
    private string $answer;

    /**
     * Rang dans sa rubrique. Les questions les plus posées se placent en tête,
     * ce que l'ordre alphabétique ne saurait pas faire.
     */
    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $position = 0;

    /**
     * Une question non publiée reste modifiable en back-office sans être
     * visible : on prépare une réponse avant l'ouverture d'une fonctionnalité.
     */
    #[ORM\Column(options: ['default' => true])]
    private bool $published = true;

    /**
     * Mise en avant sur le Centre d'aide, dans le bloc « Questions les plus
     * consultées », toutes rubriques confondues.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $featured = false;

    public function getCategory(): FaqCategory
    {
        return $this->category;
    }

    public function setCategory(FaqCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getQuestion(): string
    {
        return $this->question;
    }

    public function setQuestion(string $question): static
    {
        $this->question = $question;

        return $this;
    }

    public function getAnswer(): string
    {
        return $this->answer;
    }

    public function setAnswer(string $answer): static
    {
        $this->answer = $answer;

        return $this;
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

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): static
    {
        $this->published = $published;

        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->featured;
    }

    public function setFeatured(bool $featured): static
    {
        $this->featured = $featured;

        return $this;
    }

    /**
     * Ancre stable pour lier une question depuis l'extérieur
     * (/faq#q-01J...). L'identifiant ULID convient : il ne change jamais,
     * contrairement au libellé de la question.
     */
    public function anchor(): string
    {
        return 'q-'.strtolower((string) $this->getId());
    }
}
