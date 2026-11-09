<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Contenus des pages institutionnelles en base (09/11) : blocs (valeurs,
 * équipe, témoignages, étapes…) et offres d'emploi, auparavant figés dans
 * StaticCorporate. Le contenu de départ est chargé par app:contenu:importer.
 */
final class Version20261109140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée page_block et job_offer (contenus des pages institutionnelles).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE page_block (section VARCHAR(40) NOT NULL, title VARCHAR(180) NOT NULL, text TEXT DEFAULT NULL, author VARCHAR(120) DEFAULT NULL, role VARCHAR(160) DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, icon VARCHAR(40) DEFAULT NULL, tone VARCHAR(20) DEFAULT NULL, featured BOOLEAN DEFAULT false NOT NULL, published BOOLEAN DEFAULT true NOT NULL, position INT DEFAULT 0 NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_page_block_section ON page_block (section, position)');
        $this->addSql('CREATE TABLE job_offer (slug VARCHAR(160) NOT NULL, title VARCHAR(180) NOT NULL, summary TEXT NOT NULL, city VARCHAR(120) NOT NULL, contract VARCHAR(40) NOT NULL, work_time VARCHAR(60) NOT NULL, department VARCHAR(80) NOT NULL, manager VARCHAR(120) DEFAULT NULL, division VARCHAR(120) DEFAULT NULL, missions TEXT DEFAULT NULL, role_description TEXT DEFAULT NULL, education TEXT DEFAULT NULL, experience TEXT DEFAULT NULL, icon VARCHAR(40) DEFAULT NULL, tone VARCHAR(20) DEFAULT NULL, published BOOLEAN DEFAULT true NOT NULL, position INT DEFAULT 0 NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_288A3A4E989D9B62 ON job_offer (slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE page_block');
        $this->addSql('DROP TABLE job_offer');
    }
}
