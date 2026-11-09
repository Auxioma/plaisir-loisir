<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adhésion aux groupes (09/11).
 *
 * Le compteur de membres des groupes venait de la maquette (« 5 246
 * membres ») : il repart de la réalité, soit le créateur s'il y en a un.
 */
final class Version20261109100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée group_membership et recale le nombre de membres des groupes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE group_membership (status VARCHAR(20) NOT NULL, joined_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, group_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_5132B337FE54D947 ON group_membership (group_id)');
        $this->addSql('CREATE INDEX IDX_5132B337A76ED395 ON group_membership (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_group_membership ON group_membership (group_id, user_id)');
        $this->addSql('ALTER TABLE group_membership ADD CONSTRAINT FK_5132B337FE54D947 FOREIGN KEY (group_id) REFERENCES "group" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE group_membership ADD CONSTRAINT FK_5132B337A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('UPDATE "group" SET members_count = CASE WHEN owner_id IS NULL THEN 0 ELSE 1 END');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE group_membership DROP CONSTRAINT FK_5132B337FE54D947');
        $this->addSql('ALTER TABLE group_membership DROP CONSTRAINT FK_5132B337A76ED395');
        $this->addSql('DROP TABLE group_membership');
    }
}
