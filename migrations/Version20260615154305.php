<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260615154305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Favoris : table favorite_list et tables de jointure (listes nommées de favoris).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE favorite_list (name VARCHAR(120) NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_AACEE1277E3C61F9 ON favorite_list (owner_id)');
        $this->addSql('CREATE TABLE favorite_list_service (favorite_list_id UUID NOT NULL, service_id UUID NOT NULL, PRIMARY KEY (favorite_list_id, service_id))');
        $this->addSql('CREATE INDEX IDX_D2C445B060FAB8E5 ON favorite_list_service (favorite_list_id)');
        $this->addSql('CREATE INDEX IDX_D2C445B0ED5CA9E6 ON favorite_list_service (service_id)');
        $this->addSql('CREATE TABLE favorite_list_destination (favorite_list_id UUID NOT NULL, destination_id UUID NOT NULL, PRIMARY KEY (favorite_list_id, destination_id))');
        $this->addSql('CREATE INDEX IDX_FD95F42460FAB8E5 ON favorite_list_destination (favorite_list_id)');
        $this->addSql('CREATE INDEX IDX_FD95F424816C6140 ON favorite_list_destination (destination_id)');
        $this->addSql('ALTER TABLE favorite_list ADD CONSTRAINT FK_AACEE1277E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE favorite_list_service ADD CONSTRAINT FK_D2C445B060FAB8E5 FOREIGN KEY (favorite_list_id) REFERENCES favorite_list (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE favorite_list_service ADD CONSTRAINT FK_D2C445B0ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE favorite_list_destination ADD CONSTRAINT FK_FD95F42460FAB8E5 FOREIGN KEY (favorite_list_id) REFERENCES favorite_list (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE favorite_list_destination ADD CONSTRAINT FK_FD95F424816C6140 FOREIGN KEY (destination_id) REFERENCES destination (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE favorite_list DROP CONSTRAINT FK_AACEE1277E3C61F9');
        $this->addSql('ALTER TABLE favorite_list_service DROP CONSTRAINT FK_D2C445B060FAB8E5');
        $this->addSql('ALTER TABLE favorite_list_service DROP CONSTRAINT FK_D2C445B0ED5CA9E6');
        $this->addSql('ALTER TABLE favorite_list_destination DROP CONSTRAINT FK_FD95F42460FAB8E5');
        $this->addSql('ALTER TABLE favorite_list_destination DROP CONSTRAINT FK_FD95F424816C6140');
        $this->addSql('DROP TABLE favorite_list');
        $this->addSql('DROP TABLE favorite_list_service');
        $this->addSql('DROP TABLE favorite_list_destination');
    }
}
