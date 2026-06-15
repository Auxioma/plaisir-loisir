<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260615111250 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catalogue : champs additionnels de Service issus de la maquette (sous-titre, type d\'activité, âge minimum, programme, point de rencontre, période d\'ouverture, public cible).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE service ADD subtitle VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD activity_type VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD minimum_age INT DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD programme TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD meeting_point VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD opening_period VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD audience VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE service DROP subtitle');
        $this->addSql('ALTER TABLE service DROP activity_type');
        $this->addSql('ALTER TABLE service DROP minimum_age');
        $this->addSql('ALTER TABLE service DROP programme');
        $this->addSql('ALTER TABLE service DROP meeting_point');
        $this->addSql('ALTER TABLE service DROP opening_period');
        $this->addSql('ALTER TABLE service DROP audience');
    }
}
