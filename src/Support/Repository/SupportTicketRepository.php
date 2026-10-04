<?php

declare(strict_types=1);

namespace App\Support\Repository;

use App\Support\Entity\SupportTicket;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SupportTicket>
 */
class SupportTicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupportTicket::class);
    }

    /**
     * @return list<SupportTicket>
     */
    public function findForAuthor(User $author): array
    {
        return $this->findBy(['author' => $author], ['createdAt' => 'DESC']);
    }

    public function nextNumber(): int
    {
        $max = $this->createQueryBuilder('t')->select('MAX(t.number)')->getQuery()->getSingleScalarResult();

        return max(4300, (int) $max) + 1;
    }
}
