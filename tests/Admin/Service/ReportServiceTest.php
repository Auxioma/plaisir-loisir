<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service;

use App\Admin\Entity\AuditLog;
use App\Admin\Entity\Report;
use App\Admin\Enum\AuditAction;
use App\Admin\Enum\ReportReason;
use App\Admin\Enum\ReportStatus;
use App\Admin\Enum\ReportSubjectType;
use App\Admin\Repository\ReportRepository;
use App\Admin\Service\AuditLogger;
use App\Admin\Service\ReportService;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

/**
 * AuditLogger n'est jamais doublé (mock/stub) : c'est une classe `final`, et
 * PHPUnit ne peut pas en fabriquer une doublure. On construit une VRAIE
 * instance, adossée à un EntityManagerInterface doublé — AuditLogger ne fait
 * que persist()+flush() dessus, ce qui reste simple à observer.
 */
final class ReportServiceTest extends TestCase
{
    public function testSubmitPersistsTheReport(): void
    {
        $reporter = new User();
        $subjectId = new Ulid();

        $reports = $this->createStub(ReportRepository::class);
        $reports->method('findOnePendingFor')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(Report::class));
        $em->expects(self::once())->method('flush');

        $report = $this->service($em, $reports)->submit(
            $reporter,
            ReportSubjectType::Activity,
            $subjectId,
            'Rando du dimanche',
            ReportReason::Spam,
            'Ceci ressemble à une publicité.',
        );

        self::assertSame($reporter, $report->getReporter());
        self::assertSame('Rando du dimanche', $report->getSubjectLabel());
        self::assertSame(ReportStatus::Pending, $report->getStatus());
    }

    public function testSubmitRejectsADuplicatePendingReportFromTheSamePerson(): void
    {
        $reporter = new User();
        $subjectId = new Ulid();

        $reports = $this->createStub(ReportRepository::class);
        $reports->method('findOnePendingFor')->willReturn(new Report());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);

        $this->service($em, $reports)->submit(
            $reporter,
            ReportSubjectType::Profile,
            $subjectId,
            'Camille Aventures',
            ReportReason::Scam,
            null,
        );
    }

    public function testResolveMarksTheReportAndWritesToTheAuditLog(): void
    {
        $moderator = new User();
        $report = (new Report())->setSubjectLabel('Sortie suspecte');

        // Deux flush() attendus : un pour Report::decide(), un pour
        // AuditLogger::log() — deux écritures distinctes, volontairement (voir
        // le commentaire de ReportService::decide()).
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(AuditLog::class));
        $em->expects(self::exactly(2))->method('flush');

        $auditLogger = new AuditLogger($em);
        $service = new ReportService($em, $this->createStub(ReportRepository::class), $auditLogger);
        $service->resolve($report, $moderator, 'Compte suspendu suite à vérification.');

        self::assertSame(ReportStatus::Resolved, $report->getStatus());
        self::assertSame($moderator, $report->getProcessedBy());
        self::assertNotNull($report->getProcessedAt());
    }

    public function testDismissMarksTheReportAsDismissedAndLogsTheAction(): void
    {
        $moderator = new User();
        $report = (new Report())->setSubjectLabel('Profil douteux');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::callback(
            static fn (object $entity): bool => $entity instanceof AuditLog && AuditAction::ReportProcessed === $entity->getAction(),
        ));

        $auditLogger = new AuditLogger($em);
        (new ReportService($em, $this->createStub(ReportRepository::class), $auditLogger))
            ->dismiss($report, $moderator, 'Rien d\'anormal constaté.');

        self::assertSame(ReportStatus::Dismissed, $report->getStatus());
    }

    private function service(EntityManagerInterface $em, ReportRepository $reports): ReportService
    {
        return new ReportService($em, $reports, new AuditLogger($em));
    }
}
