<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Retours client du 07/10 : coordonnées du site administrables, niveau des
 * activités gratuites entre membres.
 */
final class Version20261009100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Coordonnées du site (company_contact), niveau des activités gratuites';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE company_contact (email VARCHAR(180) NOT NULL, phone VARCHAR(30) DEFAULT NULL, opening_hours VARCHAR(80) DEFAULT NULL, address TEXT DEFAULT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE private_activity ADD level VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE company_contact');
        $this->addSql('ALTER TABLE private_activity DROP level');
    }
}
