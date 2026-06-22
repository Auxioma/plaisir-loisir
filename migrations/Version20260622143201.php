<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260622143201 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Messagerie : tables conversation (un fil par client/annonceur) et message.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE conversation (id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, client_id UUID NOT NULL, provider_id UUID NOT NULL, service_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_8A8E26E919EB6921 ON conversation (client_id)');
        $this->addSql('CREATE INDEX IDX_8A8E26E9A53A8AA ON conversation (provider_id)');
        $this->addSql('CREATE INDEX IDX_8A8E26E9ED5CA9E6 ON conversation (service_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_conversation_client_provider ON conversation (client_id, provider_id)');
        $this->addSql('CREATE TABLE message (body TEXT NOT NULL, read_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, conversation_id UUID NOT NULL, author_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_B6BD307F9AC0396 ON message (conversation_id)');
        $this->addSql('CREATE INDEX IDX_B6BD307FF675F31B ON message (author_id)');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E919EB6921 FOREIGN KEY (client_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E9A53A8AA FOREIGN KEY (provider_id) REFERENCES provider_profile (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E9ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F9AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FF675F31B FOREIGN KEY (author_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation DROP CONSTRAINT FK_8A8E26E919EB6921');
        $this->addSql('ALTER TABLE conversation DROP CONSTRAINT FK_8A8E26E9A53A8AA');
        $this->addSql('ALTER TABLE conversation DROP CONSTRAINT FK_8A8E26E9ED5CA9E6');
        $this->addSql('ALTER TABLE message DROP CONSTRAINT FK_B6BD307F9AC0396');
        $this->addSql('ALTER TABLE message DROP CONSTRAINT FK_B6BD307FF675F31B');
        $this->addSql('DROP TABLE conversation');
        $this->addSql('DROP TABLE message');
    }
}
