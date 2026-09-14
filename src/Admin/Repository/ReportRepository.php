<?php

declare(strict_types=1);

namespace App\Admin\Repository;

use App\Admin\Entity\Report;
use App\Admin\Enum\ReportStatus;
use App\Admin\Enum\ReportSubjectType;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<Report>
 */
class ReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Report::class);
    }

    /**
     * Empêche un doublon : signaler deux fois le même contenu, pour la même
     * raison affichée à l'écran, avant qu'un premier signalement ait été
     * traité, n'apporte rien à la modération et pourrait servir à harceler.
     */
    public function findOnePendingFor(User $reporter, ReportSubjectType $subjectType, Ulid $subjectId): ?Report
    {
        return $this->findOneBy([
            'reporter' => $reporter,
            'subjectType' => $subjectType,
            'subjectId' => $subjectId,
            'status' => ReportStatus::Pending,
        ]);
    }

    /**
     * @return list<Report>
     */
    public function findPending(): array
    {
        /** @var list<Report> $results */
        $results = $this->findBy(['status' => ReportStatus::Pending], ['createdAt' => 'ASC']);

        return $results;
    }
}
