<?php

declare(strict_types=1);

namespace App\Admin\Service;

use App\Admin\Entity\AuditLog;
use App\Admin\Enum\AuditAction;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Point d'entrée unique pour historiser une opération sensible (§18.1 du
 * CDC). Volontairement minimal : un service à un seul rôle, appelé depuis
 * les écrans d'administration qui déclenchent réellement ces opérations
 * (voir UserCrudController, ReportCrudController) plutôt qu'une tentative de
 * tout intercepter automatiquement (un listener Doctrine générique aurait dû
 * deviner quelles écritures sont « sensibles » parmi toutes celles du
 * dépôt — plus fragile qu'un appel explicite au bon endroit).
 */
final class AuditLogger
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function log(
        ?User $actor,
        AuditAction $action,
        ?string $targetType = null,
        ?Ulid $targetId = null,
        ?string $targetLabel = null,
        ?string $details = null,
    ): void {
        $log = (new AuditLog())
            ->setActor($actor)
            ->setAction($action)
            ->setTargetType($targetType)
            ->setTargetId($targetId)
            ->setTargetLabel($targetLabel)
            ->setDetails($details);

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
