<?php

declare(strict_types=1);

namespace App\PrivateActivity\Repository;

use App\PrivateActivity\Entity\Album;
use App\PrivateActivity\Entity\Photo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Photo>
 */
class PhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Photo::class);
    }

    public function countForAlbum(Album $album): int
    {
        return $this->count(['album' => $album]);
    }

    public function findLatestForAlbum(Album $album): ?Photo
    {
        return $this->findOneBy(['album' => $album], ['createdAt' => 'DESC']);
    }
}
