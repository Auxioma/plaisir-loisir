<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Champs d'affichage de la carte de destination (cablage du lot 2).
 *
 * Meme demarche que sur « service » : la maquette montre des valeurs que
 * l'entite ne savait pas produire — accroche, note moyenne et nombre d'avis
 * recopies, volume d'activites annonce, prix d'appel, pastille, et l'ordre
 * des seize cartes, qu'elle fixe.
 *
 * Le volume d'activites est recopie et non compte : la maquette affiche des
 * volumes commerciaux sans rapport avec le nombre d'activites publiees.
 */
final class Version20260820221925 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute les champs d'affichage de la carte de destination (accroche, note, avis, volume, prix, pastille, ordre).";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE destination ADD tagline VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE destination ADD rating_average NUMERIC(3, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE destination ADD reviews_count INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE destination ADD activities_count INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE destination ADD price_from INT DEFAULT NULL');
        $this->addSql('ALTER TABLE destination ADD badge VARCHAR(40) DEFAULT NULL');
        $this->addSql('ALTER TABLE destination ADD position INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE destination DROP tagline');
        $this->addSql('ALTER TABLE destination DROP rating_average');
        $this->addSql('ALTER TABLE destination DROP reviews_count');
        $this->addSql('ALTER TABLE destination DROP activities_count');
        $this->addSql('ALTER TABLE destination DROP price_from');
        $this->addSql('ALTER TABLE destination DROP badge');
        $this->addSql('ALTER TABLE destination DROP position');
    }
}
