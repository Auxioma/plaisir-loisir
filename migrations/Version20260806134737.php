<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Traductions administrables en base (demande CTO du 06/08) : table
 * `translation` (locale, domain, source = texte français, translation),
 * chargée par le loader « db » de src/I18n.
 */
final class Version20260806134737 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table translation : catalogue de traductions administrable (clé = texte français source)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE translation (locale VARCHAR(5) NOT NULL, domain VARCHAR(32) DEFAULT \'messages\' NOT NULL, source TEXT NOT NULL, translation TEXT NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_translation_key ON translation (locale, domain, source)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE translation');
    }
}
