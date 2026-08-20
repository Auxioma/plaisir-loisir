<?php

declare(strict_types=1);

namespace App\Legal\Entity;

use App\Legal\Enum\LegalDocumentType;
use App\Legal\Repository\LegalDocumentRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * UNE VERSION d'un document juridique (CGU, CGV, politique de confidentialité,
 * mentions légales, politique de cookies).
 *
 * Le point essentiel : **un document publié ne se modifie jamais**. Corriger le
 * texte des CGU en place effacerait la seule chose qui compte juridiquement —
 * savoir ce que l'utilisateur a réellement accepté le jour où il a coché la
 * case. On publie donc une NOUVELLE ligne, et les acceptations passées
 * continuent de pointer vers l'ancienne.
 *
 * D'où la clé unique (type, locale, version) et l'absence de suppression douce :
 * un document juridique se retire de l'affichage, il ne s'efface pas.
 */
#[ORM\Entity(repositoryClass: LegalDocumentRepository::class)]
#[ORM\Table(name: 'legal_document')]
#[ORM\UniqueConstraint(name: 'uniq_legal_document_version', columns: ['type', 'locale', 'version'])]
#[ORM\Index(name: 'idx_legal_document_current', columns: ['type', 'locale', 'published_at'])]
#[ORM\HasLifecycleCallbacks]
class LegalDocument
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\Column(length: 40, enumType: LegalDocumentType::class)]
    private LegalDocumentType $type;

    /**
     * Langue du texte. Le site est bilingue : les CGU françaises et anglaises
     * sont deux documents distincts, chacun avec sa propre numérotation.
     */
    #[ORM\Column(length: 5)]
    #[Assert\Length(max: 5)]
    private string $locale = 'fr';

    /**
     * Numéro de version lisible par un humain (« 1.0 », « 2026-08 »).
     * Volontairement une chaîne : c'est une référence, pas un calcul.
     */
    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    private string $version;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private string $title;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private string $content;

    /**
     * Résumé des changements par rapport à la version précédente. Sert à
     * expliquer à l'utilisateur pourquoi on lui redemande son accord.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $changeSummary = null;

    /**
     * Date de publication. Tant qu'elle est nulle, le document est un
     * brouillon : il n'est ni affiché, ni opposable, ni acceptable.
     */
    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    /**
     * Date d'entrée en vigueur, qui peut être postérieure à la publication :
     * on annonce une nouvelle version puis on laisse un préavis.
     */
    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $effectiveAt = null;

    /**
     * Une nouvelle version force-t-elle les comptes existants à ré-accepter ?
     * Une correction de faute de frappe : non. Un changement de traitement des
     * données : oui.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $requiresReacceptance = false;

    public function getType(): LegalDocumentType
    {
        return $this->type;
    }

    public function setType(LegalDocumentType $type): static
    {
        $this->type = $type;

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

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): static
    {
        $this->version = $version;

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

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getChangeSummary(): ?string
    {
        return $this->changeSummary;
    }

    public function setChangeSummary(?string $changeSummary): static
    {
        $this->changeSummary = $changeSummary;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function getEffectiveAt(): ?\DateTimeImmutable
    {
        return $this->effectiveAt;
    }

    public function requiresReacceptance(): bool
    {
        return $this->requiresReacceptance;
    }

    public function setRequiresReacceptance(bool $requiresReacceptance): static
    {
        $this->requiresReacceptance = $requiresReacceptance;

        return $this;
    }

    /**
     * Publie la version. Sans date d'entrée en vigueur explicite, elle
     * s'applique immédiatement.
     */
    public function publish(?\DateTimeImmutable $effectiveAt = null): static
    {
        $now = new \DateTimeImmutable();
        $this->publishedAt = $now;
        $this->effectiveAt = $effectiveAt ?? $now;

        return $this;
    }

    public function isPublished(): bool
    {
        return null !== $this->publishedAt;
    }

    /**
     * Le document est-il opposable à cette date ? Publié ET entré en vigueur.
     */
    public function isInForce(?\DateTimeImmutable $at = null): bool
    {
        $at ??= new \DateTimeImmutable();

        return null !== $this->publishedAt
            && null !== $this->effectiveAt
            && $this->effectiveAt <= $at;
    }
}
