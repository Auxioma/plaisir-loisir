<?php

declare(strict_types=1);

namespace App\Corporate\Repository;

use App\Corporate\Entity\JobOffer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JobOffer>
 */
class JobOfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JobOffer::class);
    }

    /**
     * @return list<JobOffer>
     */
    public function findPublished(?string $department = null): array
    {
        $criteria = ['published' => true];
        if (null !== $department && '' !== $department) {
            $criteria['department'] = $department;
        }

        return $this->findBy($criteria, ['position' => 'ASC', 'createdAt' => 'ASC']);
    }

    public function findPublishedBySlug(string $slug): ?JobOffer
    {
        return $this->findOneBy(['slug' => $slug, 'published' => true]);
    }

    /**
     * Services qui ont au moins une offre publiée, pour les filtres.
     *
     * @return list<string>
     */
    public function publishedDepartments(): array
    {
        return array_values(array_unique(array_map(static fn (JobOffer $j): string => $j->getDepartment(), $this->findPublished())));
    }
}
