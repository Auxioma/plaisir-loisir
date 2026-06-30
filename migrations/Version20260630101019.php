<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260630101019 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Calendrier : table availability (créneaux horaires d\'une activité avec capacité).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE availability (starts_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, ends_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, capacity INT NOT NULL, booked INT DEFAULT 0 NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, service_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_3FB7A2BFED5CA9E6 ON availability (service_id)');
        $this->addSql('CREATE INDEX IDX_3FB7A2BF55A0507C ON availability (starts_at)');
        $this->addSql('ALTER TABLE availability ADD CONSTRAINT FK_3FB7A2BFED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE availability DROP CONSTRAINT FK_3FB7A2BFED5CA9E6');
        $this->addSql('DROP TABLE availability');
    }
}
