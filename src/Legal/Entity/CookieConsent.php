<?php

declare(strict_types=1);

namespace App\Legal\Entity;

use App\Legal\Enum\CookieCategory;
use App\Legal\Repository\CookieConsentRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Choix exprimé dans le bandeau de cookies.
 *
 * L'utilisateur peut être ANONYME : le bandeau s'affiche avant toute connexion.
 * On l'identifie alors par un jeton aléatoire déposé dans un cookie technique,
 * lui-même exempté de consentement puisqu'il ne sert qu'à mémoriser le refus.
 * Si la personne se connecte ensuite, la ligne est rattachée à son compte.
 *
 * La CNIL demande de pouvoir prouver le consentement ET de permettre de le
 * retirer aussi facilement qu'il a été donné : chaque changement d'avis crée
 * une nouvelle ligne, la plus récente fait foi. On garde l'historique.
 */
#[ORM\Entity(repositoryClass: CookieConsentRepository::class)]
#[ORM\Table(name: 'cookie_consent')]
#[ORM\Index(name: 'idx_cookie_consent_token', columns: ['visitor_token', 'decided_at'])]
#[ORM\Index(name: 'idx_cookie_consent_user', columns: ['user_id', 'decided_at'])]
#[ORM\HasLifecycleCallbacks]
class CookieConsent
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    /**
     * Jeton anonyme du visiteur. Toujours renseigné, y compris pour un membre
     * connecté : c'est lui qui relie la décision au navigateur.
     */
    #[ORM\Column(length: 64)]
    private string $visitorToken;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    /**
     * Catégories acceptées, en clair.
     *
     * « necessary » y figure toujours : ces cookies ne sont pas soumis au
     * consentement, mais les inscrire rend la ligne lisible telle quelle, sans
     * avoir à connaître la règle pour l'interpréter.
     *
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    private array $acceptedCategories = [];

    /**
     * Version de la politique de cookies en vigueur au moment du choix : un
     * consentement ne vaut que pour ce qui était annoncé ce jour-là.
     */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $policyVersion = null;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $decidedAt;

    /**
     * Échéance du consentement. La CNIL recommande de le redemander au bout de
     * treize mois ; passé cette date, le bandeau doit réapparaître.
     */
    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    public function __construct()
    {
        $this->decidedAt = new \DateTimeImmutable();
        $this->expiresAt = $this->decidedAt->modify('+13 months');
        $this->acceptedCategories = [CookieCategory::Necessary->value];
        $this->visitorToken = '';
    }

    public function getVisitorToken(): string
    {
        return $this->visitorToken;
    }

    public function setVisitorToken(string $visitorToken): static
    {
        $this->visitorToken = $visitorToken;

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

    /**
     * @return list<string>
     */
    public function getAcceptedCategories(): array
    {
        return $this->acceptedCategories;
    }

    /**
     * @param list<CookieCategory> $categories
     */
    public function setAcceptedCategories(array $categories): static
    {
        // « Nécessaires » est ajouté d'office et les doublons écartés : la
        // colonne doit rester interprétable sans post-traitement.
        $values = [CookieCategory::Necessary->value];
        foreach ($categories as $category) {
            $values[] = $category->value;
        }

        $this->acceptedCategories = array_values(array_unique($values));

        return $this;
    }

    public function accepts(CookieCategory $category): bool
    {
        return \in_array($category->value, $this->acceptedCategories, true);
    }

    public function getPolicyVersion(): ?string
    {
        return $this->policyVersion;
    }

    public function setPolicyVersion(?string $policyVersion): static
    {
        $this->policyVersion = $policyVersion;

        return $this;
    }

    public function getDecidedAt(): \DateTimeImmutable
    {
        return $this->decidedAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(?\DateTimeImmutable $at = null): bool
    {
        return $this->expiresAt <= ($at ?? new \DateTimeImmutable());
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
}
