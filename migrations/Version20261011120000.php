<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Retours client du 11/10 : nombre de vues des activités gratuites entre
 * membres (private_activity.views_count), comme pour les activités pro.
 */
final class Version20261011120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Compteur de vues des activités gratuites (private_activity.views_count)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE private_activity ADD views_count INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE private_activity DROP views_count');
    }
}
