<?php

declare(strict_types=1);

namespace App\PrivateActivity\Entity;

use App\PrivateActivity\Enum\InvitationStatus;
use App\PrivateActivity\Repository\InvitationRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Invitation d'un utilisateur à une activité privée.
 */
#[ORM\Entity(repositoryClass: InvitationRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_invitation_activity_invitee', columns: ['private_activity_id', 'invitee_id'])]
#[ORM\HasLifecycleCallbacks]
class Invitation
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: PrivateActivity::class, inversedBy: 'invitations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PrivateActivity $privateActivity = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $invitee = null;

    #[ORM\Column(enumType: InvitationStatus::class)]
    private InvitationStatus $status = InvitationStatus::Pending;

    public function getPrivateActivity(): ?PrivateActivity
    {
        return $this->privateActivity;
    }

    public function setPrivateActivity(?PrivateActivity $privateActivity): static
    {
        $this->privateActivity = $privateActivity;

        return $this;
    }

    public function getInvitee(): ?User
    {
        return $this->invitee;
    }

    public function setInvitee(?User $invitee): static
    {
        $this->invitee = $invitee;

        return $this;
    }

    public function getStatus(): InvitationStatus
    {
        return $this->status;
    }

    public function accept(): void
    {
        $this->status = InvitationStatus::Accepted;
    }

    public function decline(): void
    {
        $this->status = InvitationStatus::Declined;
    }
}
