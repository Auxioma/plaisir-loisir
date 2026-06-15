<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260615151852 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Favoris : table favorite (activité ou destination mise en favori par un utilisateur).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE favorite (id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, user_id UUID NOT NULL, service_id UUID DEFAULT NULL, destination_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_68C58ED9A76ED395 ON favorite (user_id)');
        $this->addSql('CREATE INDEX IDX_68C58ED9ED5CA9E6 ON favorite (service_id)');
        $this->addSql('CREATE INDEX IDX_68C58ED9816C6140 ON favorite (destination_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_favorite_user_service ON favorite (user_id, service_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_favorite_user_destination ON favorite (user_id, destination_id)');
        $this->addSql('ALTER TABLE favorite ADD CONSTRAINT FK_68C58ED9A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE favorite ADD CONSTRAINT FK_68C58ED9ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE favorite ADD CONSTRAINT FK_68C58ED9816C6140 FOREIGN KEY (destination_id) REFERENCES destination (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE favorite DROP CONSTRAINT FK_68C58ED9A76ED395');
        $this->addSql('ALTER TABLE favorite DROP CONSTRAINT FK_68C58ED9ED5CA9E6');
        $this->addSql('ALTER TABLE favorite DROP CONSTRAINT FK_68C58ED9816C6140');
        $this->addSql('DROP TABLE favorite');
    }
}
