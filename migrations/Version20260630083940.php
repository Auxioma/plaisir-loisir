<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260630083940 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Plaisir & Loisirs Privé : tables private_activity et invitation (sorties privées + invitations).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE invitation (status VARCHAR(255) NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, private_activity_id UUID NOT NULL, invitee_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_F11D61A24FAC41A3 ON invitation (private_activity_id)');
        $this->addSql('CREATE INDEX IDX_F11D61A27A512022 ON invitation (invitee_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_invitation_activity_invitee ON invitation (private_activity_id, invitee_id)');
        $this->addSql('CREATE TABLE private_activity (title VARCHAR(150) NOT NULL, description TEXT DEFAULT NULL, scheduled_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, organizer_id UUID NOT NULL, service_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_BE2D6F7876C4DDA ON private_activity (organizer_id)');
        $this->addSql('CREATE INDEX IDX_BE2D6F7ED5CA9E6 ON private_activity (service_id)');
        $this->addSql('ALTER TABLE invitation ADD CONSTRAINT FK_F11D61A24FAC41A3 FOREIGN KEY (private_activity_id) REFERENCES private_activity (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE invitation ADD CONSTRAINT FK_F11D61A27A512022 FOREIGN KEY (invitee_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE private_activity ADD CONSTRAINT FK_BE2D6F7876C4DDA FOREIGN KEY (organizer_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE private_activity ADD CONSTRAINT FK_BE2D6F7ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invitation DROP CONSTRAINT FK_F11D61A24FAC41A3');
        $this->addSql('ALTER TABLE invitation DROP CONSTRAINT FK_F11D61A27A512022');
        $this->addSql('ALTER TABLE private_activity DROP CONSTRAINT FK_BE2D6F7876C4DDA');
        $this->addSql('ALTER TABLE private_activity DROP CONSTRAINT FK_BE2D6F7ED5CA9E6');
        $this->addSql('DROP TABLE invitation');
        $this->addSql('DROP TABLE private_activity');
    }
}
