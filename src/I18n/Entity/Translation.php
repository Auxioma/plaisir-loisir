<?php

declare(strict_types=1);

namespace App\I18n\Entity;

use App\I18n\Repository\TranslationRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Traduction administrable en base (demande CTO du 06/08) : le client doit
 * pouvoir modifier les textes sans redéploiement. La clé (`source`) est le
 * texte français exact des maquettes ; `translation` est le texte rendu
 * pour la locale. Le catalogue est chargé par DatabaseTranslationLoader.
 */
#[ORM\Entity(repositoryClass: TranslationRepository::class)]
#[ORM\Table(name: 'translation')]
#[ORM\UniqueConstraint(name: 'uniq_translation_key', columns: ['locale', 'domain', 'source'])]
#[ORM\HasLifecycleCallbacks]
class Translation
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\Column(length: 5)]
    private string $locale;

    #[ORM\Column(length: 32, options: ['default' => 'messages'])]
    private string $domain = 'messages';

    /**
     * Clé de traduction = texte français source (peut être une phrase).
     */
    #[ORM\Column(type: Types::TEXT)]
    private string $source;

    #[ORM\Column(type: Types::TEXT)]
    private string $translation;

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): static
    {
        $this->domain = $domain;

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getTranslation(): string
    {
        return $this->translation;
    }

    public function setTranslation(string $translation): static
    {
        $this->translation = $translation;

        return $this;
    }
}
