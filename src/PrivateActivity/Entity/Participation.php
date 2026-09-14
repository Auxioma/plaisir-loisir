<?php

declare(strict_types=1);

namespace App\PrivateActivity\Entity;

use App\PrivateActivity\Enum\ParticipationStatus;
use App\PrivateActivity\Repository\ParticipationRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Demande de participation à une activité privée (§13 du CDC).
 *
 * DISTINCTE D'INVITATION
 * Invitation est l'organisateur qui va chercher un membre précis ; Participation
 * est l'inverse — un membre qui découvre une activité PUBLIQUE ou réservée aux
 * membres et demande à y participer. Les deux mènent à la même liste de
 * personnes confirmées, mais l'initiative est inversée et les règles (mode
 * automatique/validation, capacité, liste d'attente) ne s'appliquent qu'ici.
 */
#[ORM\Entity(repositoryClass: ParticipationRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_participation_activity_participant', columns: ['private_activity_id', 'participant_id'])]
#[ORM\HasLifecycleCallbacks]
class Participation
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: PrivateActivity::class, inversedBy: 'participations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PrivateActivity $privateActivity = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $participant = null;

    #[ORM\Column(enumType: ParticipationStatus::class)]
    private ParticipationStatus $status = ParticipationStatus::Pending;

    public function getPrivateActivity(): ?PrivateActivity
    {
        return $this->privateActivity;
    }

    public function setPrivateActivity(?PrivateActivity $privateActivity): static
    {
        $this->privateActivity = $privateActivity;

        return $this;
    }

    public function getParticipant(): ?User
    {
        return $this->participant;
    }

    public function setParticipant(?User $participant): static
    {
        $this->participant = $participant;

        return $this;
    }

    public function getStatus(): ParticipationStatus
    {
        return $this->status;
    }

    public function setStatus(ParticipationStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isAccepted(): bool
    {
        return ParticipationStatus::Accepted === $this->status;
    }
}
