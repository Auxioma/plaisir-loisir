<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Activités privées (05/10) : photo de couverture, heure de fin, adresse
 * géolocalisée, rendez-vous et « à apporter », saisis par le nouvel
 * assistant de création.
 */
final class Version20261005100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Activités privées : photo, fin, coordonnées, rendez-vous, à apporter';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE private_activity ADD ends_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE private_activity ADD postal_code VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE private_activity ADD latitude NUMERIC(10, 7) DEFAULT NULL');
        $this->addSql('ALTER TABLE private_activity ADD longitude NUMERIC(10, 7) DEFAULT NULL');
        $this->addSql('ALTER TABLE private_activity ADD meeting_point VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE private_activity ADD to_bring TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE private_activity ADD cover_image VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE private_activity DROP ends_at');
        $this->addSql('ALTER TABLE private_activity DROP postal_code');
        $this->addSql('ALTER TABLE private_activity DROP latitude');
        $this->addSql('ALTER TABLE private_activity DROP longitude');
        $this->addSql('ALTER TABLE private_activity DROP meeting_point');
        $this->addSql('ALTER TABLE private_activity DROP to_bring');
        $this->addSql('ALTER TABLE private_activity DROP cover_image');
    }
}
