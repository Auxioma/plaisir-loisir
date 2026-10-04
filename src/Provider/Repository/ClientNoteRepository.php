<?php

declare(strict_types=1);

namespace App\Provider\Repository;

use App\Provider\Entity\ClientNote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClientNote>
 */
class ClientNoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientNote::class);
    }
}
