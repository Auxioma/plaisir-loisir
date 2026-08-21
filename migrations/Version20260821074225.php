<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Evenements : les deux premieres tables du domaine.
 *
 *  - event_category : le badge colore de la carte. A ne pas confondre avec les
 *                     pastilles de navigation (Canoe/Kayak, VTT/Velo...), qui
 *                     forment une autre liste, editoriale.
 *  - event          : l'evenement lui-meme.
 *
 * Les dates sont de VRAIES dates, la ou la maquette n'affiche que « 15 Mai
 * 2026 » et « 9h00 - 16h00 ». Le sens de la conversion compte : d'une date on
 * refait le libelle, l'inverse est impossible — et sans date exploitable,
 * l'ecran calendrier ne peut placer aucun evenement dans une case.
 *
 * Le nombre de participants est recopie et non compte, comme les notes des
 * activites : compter a chaque carte couterait une requete par vignette.
 */
final class Version20260821074225 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Cree les tables des evenements et de leurs categories.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE event (title VARCHAR(180) NOT NULL, slug VARCHAR(200) NOT NULL, image_path VARCHAR(255) DEFAULT NULL, location VARCHAR(180) DEFAULT NULL, starts_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, ends_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, participants_count INT DEFAULT 0 NOT NULL, private BOOLEAN DEFAULT false NOT NULL, position INT DEFAULT 0 NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, category_id UUID DEFAULT NULL, organizer_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3BAE0AA7989D9B62 ON event (slug)');
        $this->addSql('CREATE INDEX IDX_3BAE0AA712469DE2 ON event (category_id)');
        $this->addSql('CREATE INDEX IDX_3BAE0AA7876C4DDA ON event (organizer_id)');
        $this->addSql('CREATE INDEX idx_event_starts_at ON event (starts_at)');
        $this->addSql('CREATE TABLE event_category (name VARCHAR(80) NOT NULL, slug VARCHAR(100) NOT NULL, color VARCHAR(20) NOT NULL, position INT DEFAULT 0 NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_40A0F011989D9B62 ON event_category (slug)');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA712469DE2 FOREIGN KEY (category_id) REFERENCES event_category (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7876C4DDA FOREIGN KEY (organizer_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP CONSTRAINT FK_3BAE0AA712469DE2');
        $this->addSql('ALTER TABLE event DROP CONSTRAINT FK_3BAE0AA7876C4DDA');
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE event_category');
    }
}
