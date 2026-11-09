<?php

declare(strict_types=1);

namespace App\Corporate\Repository;

use App\Corporate\Entity\PageBlock;
use App\Corporate\Enum\PageBlockSection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PageBlock>
 */
class PageBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageBlock::class);
    }

    /**
     * Blocs publiés d'une section, dans l'ordre d'affichage.
     *
     * @return list<PageBlock>
     */
    public function findPublished(PageBlockSection $section): array
    {
        return $this->findBy(['section' => $section, 'published' => true], ['position' => 'ASC', 'createdAt' => 'ASC']);
    }

    public function countSection(PageBlockSection $section): int
    {
        return $this->count(['section' => $section]);
    }
}
