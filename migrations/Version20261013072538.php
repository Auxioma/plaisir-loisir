<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Profil public des membres (13/10, maquettes « detail-profil » et
 * « detail-activites ») : présentation, ville et centres d'intérêt du compte,
 * avis des participants sur l'organisateur d'une activité gratuite, et
 * messagerie entre membres (destinataire « member » d'une conversation).
 */
final class Version20261013072538 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Profil public des membres : présentation, avis entre membres, messagerie entre membres';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE member_review (rating SMALLINT NOT NULL, conviviality SMALLINT NOT NULL, organisation SMALLINT NOT NULL, punctuality SMALLINT NOT NULL, communication SMALLINT NOT NULL, comment TEXT DEFAULT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, private_activity_id UUID NOT NULL, author_id UUID NOT NULL, organizer_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_5D9503F54FAC41A3 ON member_review (private_activity_id)');
        $this->addSql('CREATE INDEX IDX_5D9503F5F675F31B ON member_review (author_id)');
        $this->addSql('CREATE INDEX idx_member_review_organizer ON member_review (organizer_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_member_review_author_activity ON member_review (private_activity_id, author_id)');
        $this->addSql('ALTER TABLE member_review ADD CONSTRAINT FK_5D9503F54FAC41A3 FOREIGN KEY (private_activity_id) REFERENCES private_activity (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE member_review ADD CONSTRAINT FK_5D9503F5F675F31B FOREIGN KEY (author_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE member_review ADD CONSTRAINT FK_5D9503F5876C4DDA FOREIGN KEY (organizer_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE conversation ADD member_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE conversation ALTER provider_id DROP NOT NULL');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E97597D3FE FOREIGN KEY (member_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_8A8E26E97597D3FE ON conversation (member_id)');
        $this->addSql('ALTER TABLE "user" ADD bio TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD city VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD interests JSON DEFAULT \'[]\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member_review DROP CONSTRAINT FK_5D9503F54FAC41A3');
        $this->addSql('ALTER TABLE member_review DROP CONSTRAINT FK_5D9503F5F675F31B');
        $this->addSql('ALTER TABLE member_review DROP CONSTRAINT FK_5D9503F5876C4DDA');
        $this->addSql('DROP TABLE member_review');
        $this->addSql('ALTER TABLE conversation DROP CONSTRAINT FK_8A8E26E97597D3FE');
        $this->addSql('DROP INDEX IDX_8A8E26E97597D3FE');
        $this->addSql('ALTER TABLE conversation DROP member_id');
        $this->addSql('ALTER TABLE conversation ALTER provider_id SET NOT NULL');
        $this->addSql('ALTER TABLE "user" DROP bio');
        $this->addSql('ALTER TABLE "user" DROP city');
        $this->addSql('ALTER TABLE "user" DROP interests');
    }
}
