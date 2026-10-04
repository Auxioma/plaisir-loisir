<?php

declare(strict_types=1);

namespace App\Stats\Entity;

use App\Catalog\Entity\Service;
use App\Provider\Entity\ProviderProfile;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\Stats\Repository\PageViewRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une consultation publique comptée pour les statistiques d'un professionnel
 * (02/10) : fiche professionnelle (`profile`) ou fiche d'une activité
 * (`activity`), avec la source de trafic déduite du référent.
 *
 * Volontairement anonyme : ni utilisateur ni adresse IP, seulement de quoi
 * tracer des courbes et répartir les sources.
 */
#[ORM\Entity(repositoryClass: PageViewRepository::class)]
#[ORM\Index(columns: ['provider_id', 'viewed_at'])]
class PageView
{
    use UlidIdentifierTrait;

    public const KIND_PROFILE = 'profile';
    public const KIND_ACTIVITY = 'activity';

    public const SOURCES = [
        'search' => 'Recherche TrouveMoi',
        'direct' => 'Accès direct',
        'social' => 'Réseaux sociaux',
        'partner' => 'Sites partenaires',
        'other' => 'Autres',
    ];

    #[ORM\ManyToOne(targetEntity: ProviderProfile::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ProviderProfile $provider;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Service $service;

    #[ORM\Column(length: 20)]
    private string $kind;

    #[ORM\Column(length: 20)]
    private string $source;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $viewedAt;

    public function __construct(ProviderProfile $provider, ?Service $service, string $kind, string $source, ?\DateTimeImmutable $viewedAt = null)
    {
        $this->provider = $provider;
        $this->service = $service;
        $this->kind = $kind;
        $this->source = \array_key_exists($source, self::SOURCES) ? $source : 'other';
        $this->viewedAt = $viewedAt ?? new \DateTimeImmutable();
    }

    public function getProvider(): ProviderProfile
    {
        return $this->provider;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getViewedAt(): \DateTimeImmutable
    {
        return $this->viewedAt;
    }
}
