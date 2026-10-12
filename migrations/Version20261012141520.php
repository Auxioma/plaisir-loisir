<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Activité gratuite publiée avec une catégorie proposée (12/10) : lien vers
 * la proposition, en attente de validation par l'équipe.
 */
final class Version20261012141520 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Lien activité gratuite → catégorie proposée en attente";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE private_activity ADD category_suggestion_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE private_activity ADD CONSTRAINT FK_BE2D6F7BAC09D69 FOREIGN KEY (category_suggestion_id) REFERENCES category_suggestion (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_BE2D6F7BAC09D69 ON private_activity (category_suggestion_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE private_activity DROP CONSTRAINT FK_BE2D6F7BAC09D69');
        $this->addSql('DROP INDEX IDX_BE2D6F7BAC09D69');
        $this->addSql('ALTER TABLE private_activity DROP category_suggestion_id');
    }
}
