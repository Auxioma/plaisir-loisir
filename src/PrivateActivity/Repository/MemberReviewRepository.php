<?php

declare(strict_types=1);

namespace App\PrivateActivity\Repository;

use App\PrivateActivity\Entity\MemberReview;
use App\PrivateActivity\Entity\PrivateActivity;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MemberReview>
 */
final class MemberReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MemberReview::class);
    }

    /** @return list<MemberReview> */
    public function findForOrganizer(User $organizer, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.organizer = :o')
            ->setParameter('o', $organizer->getId(), 'ulid')
            ->orderBy('r.createdAt', 'DESC');
        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneByActivityAndAuthor(PrivateActivity $activity, User $author): ?MemberReview
    {
        return $this->findOneBy(['privateActivity' => $activity, 'author' => $author]);
    }
}
