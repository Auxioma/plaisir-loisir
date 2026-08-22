<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Champs d'affichage de la carte d'activite (cablage du lot 2).
 *
 * Six colonnes additives sur « service », pour des valeurs que la maquette
 * affiche et qu'aucun champ existant ne pouvait produire :
 *  - place_label    : « Gorges de L'Ardeche », « Museum d'Histoire Naturelle ».
 *                     Ni une ville, ni une destination : les ranger dans
 *                     « city » aurait rendu la colonne inexploitable.
 *  - duration_label : « 2h-3h », « Journee ». Aucun formatage ne produit cela a
 *                     partir d'un nombre de minutes, qui reste stocke a part
 *                     pour les tris et les filtres.
 *  - badge          : « Bestseller ».
 *  - position       : l'ordre des cartes est fixe par la maquette.
 *  - rating_average et reviews_count : agregats RECOPIES depuis la table des
 *                     avis. Sans eux, une grille de douze cartes lancerait
 *                     douze agregations a chaque affichage.
 */
final class Version20260820221132 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute les champs d'affichage de la carte d'activite (lieu, duree, badge, ordre, note moyenne, nombre d'avis).";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service ADD place_label VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD duration_label VARCHAR(40) DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD badge VARCHAR(40) DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD position INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE service ADD rating_average NUMERIC(3, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD reviews_count INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service DROP place_label');
        $this->addSql('ALTER TABLE service DROP duration_label');
        $this->addSql('ALTER TABLE service DROP badge');
        $this->addSql('ALTER TABLE service DROP position');
        $this->addSql('ALTER TABLE service DROP rating_average');
        $this->addSql('ALTER TABLE service DROP reviews_count');
    }
}
