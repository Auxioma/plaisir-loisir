<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260615121857 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catalogue : table service_option (options et compléments payants d\'une prestation).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE service_option (name VARCHAR(120) NOT NULL, description TEXT DEFAULT NULL, price NUMERIC(12, 2) NOT NULL, currency VARCHAR(3) DEFAULT \'EUR\' NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, service_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_64410586ED5CA9E6 ON service_option (service_id)');
        $this->addSql('ALTER TABLE service_option ADD CONSTRAINT FK_64410586ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE service_option DROP CONSTRAINT FK_64410586ED5CA9E6');
        $this->addSql('DROP TABLE service_option');
    }
}
