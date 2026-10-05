<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Propositions de catégories (validées dans le back-office) et brouillons
 * d'activités privées dont la catégorie peut attendre cette validation (05/10).
 */
final class Version20261005200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Propositions de catégories ; catégorie facultative pour un brouillon d\'activité privée';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE category_suggestion (name VARCHAR(80) NOT NULL, description TEXT DEFAULT NULL, context VARCHAR(10) DEFAULT \'private\' NOT NULL, status VARCHAR(10) DEFAULT \'pending\' NOT NULL, admin_note VARCHAR(255) DEFAULT NULL, decided_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, requested_by_id UUID DEFAULT NULL, parent_id UUID DEFAULT NULL, category_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_FB9EA3654DA1E751 ON category_suggestion (requested_by_id)');
        $this->addSql('CREATE INDEX IDX_FB9EA365727ACA70 ON category_suggestion (parent_id)');
        $this->addSql('CREATE INDEX IDX_FB9EA36512469DE2 ON category_suggestion (category_id)');
        $this->addSql('ALTER TABLE category_suggestion ADD CONSTRAINT FK_FB9EA3654DA1E751 FOREIGN KEY (requested_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE category_suggestion ADD CONSTRAINT FK_FB9EA365727ACA70 FOREIGN KEY (parent_id) REFERENCES category (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE category_suggestion ADD CONSTRAINT FK_FB9EA36512469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE private_activity ALTER category_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE category_suggestion');
        $this->addSql('ALTER TABLE private_activity ALTER category_id SET NOT NULL');
    }
}
