<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Index de recherche normalises sur les activites.
 *
 * Deux colonnes calculees, en minuscules et sans accents : search_text (titre,
 * accroche, categorie) et search_place (lieu affiche, ville, destination).
 *
 * Elles existent parce que « canoe » doit trouver « Descente en Canoe » ecrit
 * avec un trema. PostgreSQL sait le faire avec l'extension unaccent, mais son
 * installation reclame des droits que le compte applicatif n'a pas forcement
 * en production : le deploiement echouerait. Une colonne calculee marche
 * partout, sans rien demander a l'hebergeur.
 *
 * Elles sont remplies a chaque enregistrement (Service::refreshSearchIndex).
 * Les lignes existantes restent nulles jusqu'a leur prochaine ecriture : sur
 * une base deja peuplee, prevoir un passage de mise a jour.
 */
final class Version20260821055435 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute les index de recherche normalises (sans accents) sur les activites.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service ADD search_text TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD search_place TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service DROP search_text');
        $this->addSql('ALTER TABLE service DROP search_place');
    }
}
