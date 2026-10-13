<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261013112204 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Activités pro : catégorie proposée en attente de validation';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE service ADD category_suggestion_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE service ALTER category_id DROP NOT NULL');
        $this->addSql('ALTER TABLE service ADD CONSTRAINT FK_E19D9AD2BAC09D69 FOREIGN KEY (category_suggestion_id) REFERENCES category_suggestion (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_E19D9AD2BAC09D69 ON service (category_suggestion_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE service DROP CONSTRAINT FK_E19D9AD2BAC09D69');
        $this->addSql('DROP INDEX IDX_E19D9AD2BAC09D69');
        $this->addSql('ALTER TABLE service DROP category_suggestion_id');
        $this->addSql('ALTER TABLE service ALTER category_id SET NOT NULL');
    }
}
