<?php

declare(strict_types=1);

namespace App\Catalog\Service;

use App\Catalog\Entity\Service;
use App\Catalog\Enum\ServiceStatus;
use App\Provider\Enum\ProviderStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Logique métier de publication d'une activité par un annonceur.
 */
final class ActivityPublishingService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Publie une activité en brouillon (draft -> published).
     *
     * @throws \InvalidArgumentException si l'annonceur n'est pas vérifié ou si
     *                                   l'activité n'est pas en brouillon
     */
    public function publish(Service $service): void
    {
        $provider = $service->getProvider();
        if (null === $provider || ProviderStatus::Verified !== $provider->getStatus()) {
            throw new \InvalidArgumentException('Seul un annonceur vérifié peut publier une activité.');
        }

        if (!\in_array($service->getStatus(), [ServiceStatus::Draft, ServiceStatus::Pending], true)) {
            throw new \InvalidArgumentException('Seule une activité en brouillon ou en attente peut être publiée.');
        }

        $service->setStatus(ServiceStatus::Published);
        $this->entityManager->flush();
    }

    /**
     * Retire une activité du catalogue (archived).
     */
    /**
     * Le professionnel soumet son activité : elle passe « En attente » de
     * validation par l'équipe (back-office, action « Valider »). Même double
     * verrou que publish() : seul un annonceur vérifié peut soumettre.
     */
    public function submit(Service $service): void
    {
        $provider = $service->getProvider();
        if (null === $provider || ProviderStatus::Verified !== $provider->getStatus()) {
            throw new \InvalidArgumentException('Votre compte professionnel doit être vérifié avant de publier une activité.');
        }

        if (!\in_array($service->getStatus(), [ServiceStatus::Draft, ServiceStatus::Archived], true)) {
            throw new \InvalidArgumentException('Seule une activité en brouillon peut être soumise.');
        }

        $service->setStatus(ServiceStatus::Pending);
        $this->entityManager->flush();
    }

    /** Repasse une activité en brouillon (retrait de la publication par le professionnel). */
    public function unpublish(Service $service): void
    {
        if (ServiceStatus::Suspended === $service->getStatus()) {
            throw new \InvalidArgumentException('Une activité suspendue par l\'équipe ne peut pas être modifiée.');
        }

        $service->setStatus(ServiceStatus::Draft);
        $this->entityManager->flush();
    }

    public function suspend(Service $service): void
    {
        $service->setStatus(ServiceStatus::Suspended);
        $this->entityManager->flush();
    }

    public function archive(Service $service): void
    {
        $service->setStatus(ServiceStatus::Archived);
        $this->entityManager->flush();
    }
}
