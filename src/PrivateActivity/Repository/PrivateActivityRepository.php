<?php

declare(strict_types=1);

namespace App\PrivateActivity\Repository;

use App\PrivateActivity\Entity\PrivateActivity;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PrivateActivity>
 */
class PrivateActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PrivateActivity::class);
    }

    /**
     * @return PrivateActivity[]
     */
    public function findByOrganizer(User $organizer): array
    {
        return $this->findBy(['organizer' => $organizer], ['createdAt' => 'DESC']);
    }
}
