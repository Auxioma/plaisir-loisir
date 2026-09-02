<?php

declare(strict_types=1);

namespace App\Provider\Entity;

use App\Provider\Enum\ProviderDocumentKind;
use App\Provider\Repository\ProviderDocumentRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * UNE pièce justificative déposée par un professionnel à l'inscription.
 *
 * CE QUI EST STOCKÉ ICI, ET CE QUI NE L'EST PAS
 * La table ne contient QUE des métadonnées : le fichier lui-même vit dans
 * `var/uploads/provider-documents/`, en dehors de la racine web. C'est une
 * différence de fond avec les photos du catalogue, qui atterrissent dans
 * `public/uploads/` : une photo d'activité est faite pour être vue de tous,
 * un extrait Kbis ou un certificat d'assurance ne l'est pas. Servi depuis
 * `public/`, il serait accessible à quiconque devine son nom.
 *
 * Le nom d'origine est conservé à part du nom de stockage : le premier est
 * celui que le prestataire reconnaîtra dans le back-office, le second est
 * tiré au sort pour que personne ne puisse énumérer les fichiers.
 */
#[ORM\Entity(repositoryClass: ProviderDocumentRepository::class)]
#[ORM\Table(name: 'provider_document')]
#[ORM\Index(name: 'idx_provider_document_profile', columns: ['provider_profile_id'])]
#[ORM\HasLifecycleCallbacks]
class ProviderDocument
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ProviderProfile $providerProfile = null;

    #[ORM\Column(length: 40, enumType: ProviderDocumentKind::class)]
    private ProviderDocumentKind $kind = ProviderDocumentKind::Other;

    /** Nom du fichier tel que le prestataire l'a déposé. */
    #[ORM\Column(length: 255)]
    private string $originalName = '';

    /** Nom tiré au sort sous lequel le fichier est rangé sur le disque. */
    #[ORM\Column(length: 120)]
    private string $storedName = '';

    #[ORM\Column(length: 100)]
    private string $mimeType = 'application/octet-stream';

    #[ORM\Column]
    private int $sizeBytes = 0;

    public function getProviderProfile(): ?ProviderProfile
    {
        return $this->providerProfile;
    }

    public function setProviderProfile(?ProviderProfile $providerProfile): static
    {
        $this->providerProfile = $providerProfile;

        return $this;
    }

    public function getKind(): ProviderDocumentKind
    {
        return $this->kind;
    }

    public function setKind(ProviderDocumentKind $kind): static
    {
        $this->kind = $kind;

        return $this;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): static
    {
        $this->originalName = mb_substr($originalName, 0, 255);

        return $this;
    }

    public function getStoredName(): string
    {
        return $this->storedName;
    }

    public function setStoredName(string $storedName): static
    {
        $this->storedName = $storedName;

        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = mb_substr($mimeType, 0, 100);

        return $this;
    }

    public function getSizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function setSizeBytes(int $sizeBytes): static
    {
        $this->sizeBytes = $sizeBytes;

        return $this;
    }

    /**
     * Taille lisible, pour le back-office : « 1,4 Mo » plutôt que « 1468006 ».
     */
    public function getReadableSize(): string
    {
        if ($this->sizeBytes < 1024) {
            return $this->sizeBytes.' o';
        }

        if ($this->sizeBytes < 1024 * 1024) {
            return number_format($this->sizeBytes / 1024, 0, ',', ' ').' Ko';
        }

        return number_format($this->sizeBytes / (1024 * 1024), 1, ',', ' ').' Mo';
    }
}
