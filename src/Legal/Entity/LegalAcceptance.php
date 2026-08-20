<?php

declare(strict_types=1);

namespace App\Legal\Entity;

use App\Legal\Enum\ConsentSource;
use App\Legal\Repository\LegalAcceptanceRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Preuve qu'un utilisateur a accepté UNE VERSION PRÉCISE d'un document.
 *
 * Jusqu'ici, la case « J'accepte les conditions générales » était validée puis
 * oubliée : rien en base ne permettait de démontrer que qui que ce soit avait
 * accepté quoi que ce soit. L'article 7.1 du RGPD exige pourtant du responsable
 * de traitement qu'il soit « en mesure de démontrer que la personne a donné son
 * consentement ».
 *
 * On enregistre donc les quatre éléments qui font la preuve : QUI, QUOI (la
 * version exacte, pas le document en général), QUAND, et DEPUIS OÙ.
 *
 * Cette table ne se met jamais à jour et ne se supprime jamais : une nouvelle
 * acceptation crée une nouvelle ligne. L'historique complet est la preuve.
 */
#[ORM\Entity(repositoryClass: LegalAcceptanceRepository::class)]
#[ORM\Table(name: 'legal_acceptance')]
#[ORM\Index(name: 'idx_legal_acceptance_user', columns: ['user_id', 'accepted_at'])]
#[ORM\HasLifecycleCallbacks]
class LegalAcceptance
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    /**
     * La version acceptée. `onDelete: RESTRICT` est délibéré : supprimer une
     * version de CGU encore référencée par des acceptations détruirait la
     * preuve. La base doit s'y opposer.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?LegalDocument $document = null;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $acceptedAt;

    #[ORM\Column(length: 30, enumType: ConsentSource::class)]
    private ConsentSource $source = ConsentSource::Registration;

    /**
     * Adresse IP au moment du consentement. 45 caractères : la longueur d'une
     * adresse IPv6 sous sa forme la plus longue.
     *
     * C'est une donnée personnelle, collectée pour la seule constitution de la
     * preuve. Elle doit être purgée avec le compte.
     */
    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent = null;

    public function __construct()
    {
        $this->acceptedAt = new \DateTimeImmutable();
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

    public function getDocument(): ?LegalDocument
    {
        return $this->document;
    }

    public function setDocument(?LegalDocument $document): static
    {
        $this->document = $document;

        return $this;
    }

    public function getAcceptedAt(): \DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function getSource(): ConsentSource
    {
        return $this->source;
    }

    public function setSource(ConsentSource $source): static
    {
        $this->source = $source;

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

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        // Les agents utilisateurs dépassent régulièrement 255 caractères ;
        // on tronque plutôt que de laisser la base rejeter l'insertion et
        // faire échouer une inscription pour un motif aussi accessoire.
        $this->userAgent = null !== $userAgent ? mb_substr($userAgent, 0, 255) : null;

        return $this;
    }
}
