<?php

declare(strict_types=1);

namespace App\Admin\Entity;

use App\Admin\Enum\ReportReason;
use App\Admin\Enum\ReportStatus;
use App\Admin\Enum\ReportSubjectType;
use App\Admin\Repository\ReportRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Signalement d'un contenu ou d'un comportement (§16.4, §18 du CDC).
 *
 * GÉNÉRIQUE, PAS UNE RELATION PAR TYPE
 * Le contenu visé peut être un profil, une activité, un avis, un message ou
 * une photo — cinq tables différentes. Une colonne de clé étrangère par type
 * aurait exigé que quatre restent NULL sur chaque ligne, pour un type qui
 * grandira encore (§16.4 le dit « ou autre motif »). `subjectType` +
 * `subjectId` (sans contrainte de clé étrangère, par nature) suffisent.
 *
 * `subjectLabel` FIGE un intitulé lisible AU MOMENT DU SIGNALEMENT — même
 * principe que BookingItem (voir CLAUDE.md, « snapshots ») : si le profil ou
 * l'activité visée est ensuite modifiée, supprimée ou anonymisée, le
 * signalement reste compréhensible sans jointure vers une ligne qui peut ne
 * plus exister.
 */
#[ORM\Entity(repositoryClass: ReportRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Report
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $reporter = null;

    #[ORM\Column(enumType: ReportSubjectType::class)]
    private ReportSubjectType $subjectType;

    #[ORM\Column(type: 'ulid')]
    private Ulid $subjectId;

    #[ORM\Column(length: 180)]
    private string $subjectLabel;

    #[ORM\Column(enumType: ReportReason::class)]
    private ReportReason $reason;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column(enumType: ReportStatus::class)]
    private ReportStatus $status = ReportStatus::Pending;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $moderatorNote = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $processedBy = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    public function getReporter(): ?User
    {
        return $this->reporter;
    }

    public function setReporter(?User $reporter): static
    {
        $this->reporter = $reporter;

        return $this;
    }

    public function getSubjectType(): ReportSubjectType
    {
        return $this->subjectType;
    }

    public function setSubjectType(ReportSubjectType $subjectType): static
    {
        $this->subjectType = $subjectType;

        return $this;
    }

    public function getSubjectId(): Ulid
    {
        return $this->subjectId;
    }

    public function setSubjectId(Ulid $subjectId): static
    {
        $this->subjectId = $subjectId;

        return $this;
    }

    public function getSubjectLabel(): string
    {
        return $this->subjectLabel;
    }

    public function setSubjectLabel(string $subjectLabel): static
    {
        $this->subjectLabel = $subjectLabel;

        return $this;
    }

    public function getReason(): ReportReason
    {
        return $this->reason;
    }

    public function setReason(ReportReason $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getStatus(): ReportStatus
    {
        return $this->status;
    }

    public function setStatus(ReportStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getModeratorNote(): ?string
    {
        return $this->moderatorNote;
    }

    public function setModeratorNote(?string $moderatorNote): static
    {
        $this->moderatorNote = $moderatorNote;

        return $this;
    }

    public function getProcessedBy(): ?User
    {
        return $this->processedBy;
    }

    public function setProcessedBy(?User $processedBy): static
    {
        $this->processedBy = $processedBy;

        return $this;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTimeImmutable $processedAt): static
    {
        $this->processedAt = $processedAt;

        return $this;
    }
}
