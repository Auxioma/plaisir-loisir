<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fiche detaillee d'une activite (cablage du lot 2).
 *
 * Une table a part, reliee un a un a « service » : une quinzaine de champs qui
 * ne servent qu'a un seul ecran et ne sont jamais interroges n'ont pas a
 * alourdir chaque requete de listing.
 *
 * Les listes (inclus, non inclus, a apporter, points de rendez-vous,
 * garanties) sont en JSON : ce sont des suites de phrases ordonnees, sans
 * identite propre, jamais recherchees ni partagees. Une table par liste aurait
 * produit cinq jointures a chaque affichage sans rien apporter.
 */
final class Version20260820224235 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute la table du contenu editorial de la fiche detaillee d'activite.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE service_detail (breadcrumb JSON NOT NULL, organizer VARCHAR(120) DEFAULT NULL, presentation_subtitle VARCHAR(255) DEFAULT NULL, presentation_text TEXT DEFAULT NULL, highlights_title VARCHAR(180) DEFAULT NULL, highlights JSON NOT NULL, included JSON NOT NULL, excluded JSON NOT NULL, cannot_participate JSON NOT NULL, to_bring JSON NOT NULL, key_facts JSON NOT NULL, map_image VARCHAR(255) DEFAULT NULL, meeting_points JSON NOT NULL, guarantees JSON NOT NULL, price INT DEFAULT NULL, reviews_score VARCHAR(10) DEFAULT NULL, reviews_out_of INT DEFAULT NULL, reviews_total INT DEFAULT NULL, modal_title VARCHAR(255) DEFAULT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, service_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_10C17AA5ED5CA9E6 ON service_detail (service_id)');
        $this->addSql('ALTER TABLE service_detail ADD CONSTRAINT FK_10C17AA5ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service_detail DROP CONSTRAINT FK_10C17AA5ED5CA9E6');
        $this->addSql('DROP TABLE service_detail');
    }
}
