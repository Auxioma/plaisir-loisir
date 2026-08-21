<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Groupes et albums photos.
 *
 * Le nom de la table est ENTRE GUILLEMETS : « group » est un mot reserve du
 * SQL (GROUP BY), et sans les guillemets PostgreSQL refuse la moindre requete
 * sur cette table.
 *
 * Le nombre de membres et le nombre de photos sont recopies plutot que
 * comptes, comme les autres volumes du site : la maquette affiche des chiffres
 * commerciaux (5 246 membres) et compter a chaque carte couterait une requete
 * par vignette. Les photos, elles, n'ont pas encore d'entite : la maquette
 * n'en montre que la vignette de couverture et un decompte.
 */
final class Version20260821181646 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cree les tables des groupes et de leurs albums photos.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE "group" (name VARCHAR(180) NOT NULL, slug VARCHAR(200) NOT NULL, description TEXT DEFAULT NULL, image_path VARCHAR(255) DEFAULT NULL, location VARCHAR(180) DEFAULT NULL, members_count INT DEFAULT 0 NOT NULL, badge VARCHAR(40) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, owner_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6DC044C5989D9B62 ON "group" (slug)');
        $this->addSql('CREATE INDEX IDX_6DC044C57E3C61F9 ON "group" (owner_id)');
        $this->addSql('CREATE TABLE group_album (title VARCHAR(180) NOT NULL, location VARCHAR(180) DEFAULT NULL, image_path VARCHAR(255) DEFAULT NULL, photos_count INT DEFAULT 0 NOT NULL, last_photo_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, position INT DEFAULT 0 NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, group_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_69B44524FE54D947 ON group_album (group_id)');
        $this->addSql('ALTER TABLE "group" ADD CONSTRAINT FK_6DC044C57E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE group_album ADD CONSTRAINT FK_69B44524FE54D947 FOREIGN KEY (group_id) REFERENCES "group" (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "group" DROP CONSTRAINT FK_6DC044C57E3C61F9');
        $this->addSql('ALTER TABLE group_album DROP CONSTRAINT FK_69B44524FE54D947');
        $this->addSql('DROP TABLE "group"');
        $this->addSql('DROP TABLE group_album');
    }
}
