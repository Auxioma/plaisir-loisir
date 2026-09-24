<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260917151714 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Album photo des activités privées (§4 de docs/corrections-client-2026-07-27.md).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE album (id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, private_activity_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_39986E434FAC41A3 ON album (private_activity_id)');
        $this->addSql('CREATE TABLE photo (path VARCHAR(255) NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, album_id UUID NOT NULL, author_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_14B784181137ABCF ON photo (album_id)');
        $this->addSql('CREATE INDEX IDX_14B78418F675F31B ON photo (author_id)');
        $this->addSql('ALTER TABLE album ADD CONSTRAINT FK_39986E434FAC41A3 FOREIGN KEY (private_activity_id) REFERENCES private_activity (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT FK_14B784181137ABCF FOREIGN KEY (album_id) REFERENCES album (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE photo ADD CONSTRAINT FK_14B78418F675F31B FOREIGN KEY (author_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE album DROP CONSTRAINT FK_39986E434FAC41A3');
        $this->addSql('ALTER TABLE photo DROP CONSTRAINT FK_14B784181137ABCF');
        $this->addSql('ALTER TABLE photo DROP CONSTRAINT FK_14B78418F675F31B');
        $this->addSql('DROP TABLE album');
        $this->addSql('DROP TABLE photo');
    }
}
