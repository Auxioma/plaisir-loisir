<?php

declare(strict_types=1);

namespace App\Admin\Service;

use App\Admin\Entity\Report;
use App\Admin\Enum\AuditAction;
use App\Admin\Enum\ReportReason;
use App\Admin\Enum\ReportStatus;
use App\Admin\Enum\ReportSubjectType;
use App\Admin\Repository\ReportRepository;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Logique métier des signalements (§16.4, §18 du CDC).
 */
final class ReportService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReportRepository $reports,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * @throws \InvalidArgumentException si le contenu est déjà signalé par la même
     *                                   personne et en attente de traitement
     */
    public function submit(
        User $reporter,
        ReportSubjectType $subjectType,
        Ulid $subjectId,
        string $subjectLabel,
        ReportReason $reason,
        ?string $message,
    ): Report {
        if (null !== $this->reports->findOnePendingFor($reporter, $subjectType, $subjectId)) {
            throw new \InvalidArgumentException('Vous avez déjà signalé ce contenu ; la modération va l\'examiner.');
        }

        $report = (new Report())
            ->setReporter($reporter)
            ->setSubjectType($subjectType)
            ->setSubjectId($subjectId)
            ->setSubjectLabel(mb_substr($subjectLabel, 0, 180))
            ->setReason($reason)
            ->setMessage($message);

        $this->entityManager->persist($report);
        $this->entityManager->flush();

        return $report;
    }

    public function resolve(Report $report, User $moderator, ?string $note): void
    {
        $this->decide($report, $moderator, ReportStatus::Resolved, $note);
    }

    public function dismiss(Report $report, User $moderator, ?string $note): void
    {
        $this->decide($report, $moderator, ReportStatus::Dismissed, $note);
    }

    private function decide(Report $report, User $moderator, ReportStatus $status, ?string $note): void
    {
        $report
            ->setStatus($status)
            ->setModeratorNote($note)
            ->setProcessedBy($moderator)
            ->setProcessedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        $this->auditLogger->log(
            actor: $moderator,
            action: AuditAction::ReportProcessed,
            targetType: 'Report',
            targetId: $report->getId(),
            targetLabel: $report->getSubjectLabel(),
            details: sprintf('%s : %s', ReportStatus::Resolved === $status ? 'traité' : 'classé sans suite', $note ?? '—'),
        );
    }
}
