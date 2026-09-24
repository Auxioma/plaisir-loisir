<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Activités privées entre particuliers, conformes au CDC (§12-13) et non plus
 * seulement au modèle « invitation directe » qui existait déjà.
 *
 * `participation` — la table qui manquait pour la découverte publique : un
 * membre demande à rejoindre une activité PUBLIQUE ou réservée aux membres
 * (au lieu d'être invité nommément), avec capacité et liste d'attente
 * (PrivateActivityService).
 *
 * Colonnes NOT NULL sans valeur par défaut sur `private_activity`
 * (catégorie, visibilité, mode d'inscription, statut) : la table est encore
 * vide à ce stade du projet (aucune préproduction, aucune donnée réelle,
 * vérifié avant d'écrire cette migration) — pas besoin d'une migration en
 * deux temps comme il en faudrait une fois des lignes réelles en jeu.
 */
final class Version20260914131931 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Activités privées : catégorie, visibilité, capacité, mode d\'inscription, et la table participation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE participation (
              status VARCHAR(255) NOT NULL,
              id UUID NOT NULL,
              created_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                private_activity_id UUID NOT NULL,
                participant_id UUID NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_AB55E24F4FAC41A3 ON participation (private_activity_id)');
        $this->addSql('CREATE INDEX IDX_AB55E24F9D1C3019 ON participation (participant_id)');
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_participation_activity_participant ON participation (
              private_activity_id, participant_id
            )
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              participation
            ADD
              CONSTRAINT FK_AB55E24F4FAC41A3 FOREIGN KEY (private_activity_id) REFERENCES private_activity (id) ON DELETE CASCADE NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              participation
            ADD
              CONSTRAINT FK_AB55E24F9D1C3019 FOREIGN KEY (participant_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE
        SQL);
        $this->addSql('ALTER TABLE private_activity ADD city VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE private_activity ADD show_exact_address BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE private_activity ADD visibility VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE private_activity ADD participation_mode VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE private_activity ADD status VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE private_activity ADD min_participants INT DEFAULT NULL');
        $this->addSql('ALTER TABLE private_activity ADD max_participants INT DEFAULT NULL');
        $this->addSql('ALTER TABLE private_activity ADD category_id UUID NOT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              private_activity
            ADD
              CONSTRAINT FK_BE2D6F712469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE NOT DEFERRABLE
        SQL);
        $this->addSql('CREATE INDEX IDX_BE2D6F712469DE2 ON private_activity (category_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE participation DROP CONSTRAINT FK_AB55E24F4FAC41A3');
        $this->addSql('ALTER TABLE participation DROP CONSTRAINT FK_AB55E24F9D1C3019');
        $this->addSql('DROP TABLE participation');
        $this->addSql('ALTER TABLE private_activity DROP CONSTRAINT FK_BE2D6F712469DE2');
        $this->addSql('DROP INDEX IDX_BE2D6F712469DE2');
        $this->addSql('ALTER TABLE private_activity DROP city');
        $this->addSql('ALTER TABLE private_activity DROP show_exact_address');
        $this->addSql('ALTER TABLE private_activity DROP visibility');
        $this->addSql('ALTER TABLE private_activity DROP participation_mode');
        $this->addSql('ALTER TABLE private_activity DROP status');
        $this->addSql('ALTER TABLE private_activity DROP min_participants');
        $this->addSql('ALTER TABLE private_activity DROP max_participants');
        $this->addSql('ALTER TABLE private_activity DROP category_id');
    }
}
