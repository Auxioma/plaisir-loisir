<?php

declare(strict_types=1);

namespace App\PrivateActivity\Repository;

use App\PrivateActivity\Entity\Album;
use App\PrivateActivity\Entity\PrivateActivity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Album>
 */
class AlbumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Album::class);
    }

    public function findOneByActivity(PrivateActivity $activity): ?Album
    {
        return $this->findOneBy(['privateActivity' => $activity]);
    }
}
