<?php

declare(strict_types=1);

namespace App\I18n\Repository;

use App\I18n\Entity\Translation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Translation>
 */
final class TranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Translation::class);
    }

    /**
     * Catalogue complet d'une locale : ['texte français' => 'traduction'].
     *
     * @return array<string, string>
     */
    public function findCatalogue(string $locale, string $domain = 'messages'): array
    {
        $catalogue = [];
        /** @var Translation $row */
        foreach ($this->findBy(['locale' => $locale, 'domain' => $domain]) as $row) {
            $catalogue[$row->getSource()] = $row->getTranslation();
        }

        return $catalogue;
    }
}
