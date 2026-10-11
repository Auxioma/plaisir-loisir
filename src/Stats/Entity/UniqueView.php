<?php

declare(strict_types=1);

namespace App\Stats\Entity;

use App\Shared\Doctrine\UlidIdentifierTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Trace qu'une personne a déjà consulté une page (11/10) : le nombre de vues
 * affiché compte des PERSONNES, pas des clics. Une ligne par couple
 * (page, visiteur) ; la contrainte d'unicité fait le tri.
 *
 * `visitor` est une empreinte HMAC : l'identifiant du membre connecté, ou
 * l'adresse IP + le navigateur d'un visiteur anonyme. Ni l'un ni l'autre
 * n'est stocké en clair.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_unique_view_visitor', columns: ['kind', 'target_id', 'visitor'])]
class UniqueView
{
    use UlidIdentifierTrait;

    public const KIND_PRIVATE_ACTIVITY = 'private_activity';

    #[ORM\Column(length: 20)]
    private string $kind;

    #[ORM\Column(type: 'ulid')]
    private Ulid $targetId;

    #[ORM\Column(length: 64)]
    private string $visitor;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $viewedAt;

    public function __construct(string $kind, Ulid $targetId, string $visitor)
    {
        $this->kind = $kind;
        $this->targetId = $targetId;
        $this->visitor = $visitor;
        $this->viewedAt = new \DateTimeImmutable();
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getTargetId(): Ulid
    {
        return $this->targetId;
    }

    public function getVisitor(): string
    {
        return $this->visitor;
    }

    public function getViewedAt(): \DateTimeImmutable
    {
        return $this->viewedAt;
    }
}
