<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260615160248 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Favoris : table favorite_share (lien de partage à jeton + visibilité privée/publique/communauté).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE favorite_share (token VARCHAR(64) NOT NULL, visibility VARCHAR(255) NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, owner_id UUID NOT NULL, list_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_598EB67E5F37A13B ON favorite_share (token)');
        $this->addSql('CREATE INDEX IDX_598EB67E7E3C61F9 ON favorite_share (owner_id)');
        $this->addSql('CREATE INDEX IDX_598EB67E3DAE168B ON favorite_share (list_id)');
        $this->addSql('ALTER TABLE favorite_share ADD CONSTRAINT FK_598EB67E7E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE favorite_share ADD CONSTRAINT FK_598EB67E3DAE168B FOREIGN KEY (list_id) REFERENCES favorite_list (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE favorite_share DROP CONSTRAINT FK_598EB67E7E3C61F9');
        $this->addSql('ALTER TABLE favorite_share DROP CONSTRAINT FK_598EB67E3DAE168B');
        $this->addSql('DROP TABLE favorite_share');
    }
}
