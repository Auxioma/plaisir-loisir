<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Performance (12/10) : l'illustration de Paris passe d'un PNG de 977 Ko à un
 * WebP de 30 Ko. Les destinations déjà en base pointent vers le nouveau
 * fichier (l'ancien reste servi pour les liens existants).
 */
final class Version20261012200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Image de Paris en WebP pour les destinations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE destination SET hero_image = REPLACE(hero_image, 'images/home/dest-paris.png', 'images/home/dest-paris.webp') WHERE hero_image LIKE '%images/home/dest-paris.png%'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE destination SET hero_image = REPLACE(hero_image, 'images/home/dest-paris.webp', 'images/home/dest-paris.png') WHERE hero_image LIKE '%images/home/dest-paris.webp%'");
    }
}
