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

        if (ServiceStatus::Draft !== $service->getStatus()) {
            throw new \InvalidArgumentException('Seule une activité en brouillon peut être publiée.');
        }

        $service->setStatus(ServiceStatus::Published);
        $this->entityManager->flush();
    }

    /**
     * Retire une activité du catalogue (archived).
     */
    public function archive(Service $service): void
    {
        $service->setStatus(ServiceStatus::Archived);
        $this->entityManager->flush();
    }
}
