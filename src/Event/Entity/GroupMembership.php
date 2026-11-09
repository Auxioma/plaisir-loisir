<?php

declare(strict_types=1);

namespace App\Event\Entity;

use App\Event\Repository\GroupMembershipRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Adhésion d'un membre à un groupe (09/11).
 *
 * « Demander à rejoindre le groupe » crée une demande (pending) que le
 * créateur du groupe accepte ou refuse ; un groupe sans créateur (groupes de
 * démonstration) accepte directement. Le créateur lui-même n'a pas de ligne :
 * il est membre de fait, via Group::$owner.
 */
#[ORM\Entity(repositoryClass: GroupMembershipRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_group_membership', columns: ['group_id', 'user_id'])]
#[ORM\HasLifecycleCallbacks]
class GroupMembership
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    public const PENDING = 'pending';
    public const MEMBER = 'member';

    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Group $group;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 20)]
    private string $status;

    /** Date d'entrée dans le groupe : « a rejoint le … » de l'onglet Membres. */
    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $joinedAt = null;

    public function __construct(Group $group, User $user, string $status = self::PENDING)
    {
        $this->group = $group;
        $this->user = $user;
        $this->status = $status;
        if (self::MEMBER === $status) {
            $this->joinedAt = new \DateTimeImmutable();
        }
    }

    public function getGroup(): Group
    {
        return $this->group;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isPending(): bool
    {
        return self::PENDING === $this->status;
    }

    public function accept(): static
    {
        $this->status = self::MEMBER;
        $this->joinedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getJoinedAt(): ?\DateTimeImmutable
    {
        return $this->joinedAt;
    }
}
