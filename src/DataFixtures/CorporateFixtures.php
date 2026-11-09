<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Corporate\Service\CorporateContentImporter;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Contenus des pages institutionnelles : le même import qu'en production
 * (app:contenu:importer), pour que le développement et les tests partent du
 * même contenu.
 */
class CorporateFixtures extends Fixture
{
    public function __construct(private readonly CorporateContentImporter $importer)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $this->importer->import();
    }
}
