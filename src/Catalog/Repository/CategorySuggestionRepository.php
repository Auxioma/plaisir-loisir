<?php

declare(strict_types=1);

namespace App\Catalog\Repository;

use App\Catalog\Entity\CategorySuggestion;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CategorySuggestion>
 */
class CategorySuggestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategorySuggestion::class);
    }

    /** @return list<CategorySuggestion> propositions de ce membre, plus récentes d'abord */
    public function findForUser(User $user): array
    {
        /** @var list<CategorySuggestion> $rows */
        $rows = $this->createQueryBuilder('s')
            ->andWhere('s.requestedBy = :user')
            ->setParameter('user', $user->getId(), 'ulid')
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()->getResult();

        return $rows;
    }

    public function findPendingByName(string $name): ?CategorySuggestion
    {
        return $this->createQueryBuilder('s')
            ->andWhere('LOWER(s.name) = :name')
            ->andWhere('s.status = :pending')
            ->setParameter('name', mb_strtolower(trim($name)))
            ->setParameter('pending', CategorySuggestion::STATUS_PENDING)
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }
}
