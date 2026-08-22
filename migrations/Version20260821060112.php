<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Index de recherche normalise sur les destinations.
 *
 * Meme dispositif que sur « service » et pour la meme raison : « egypte » doit
 * trouver « Egypte » accentue, sans dependre d'une extension PostgreSQL que le
 * compte applicatif n'a peut-etre pas le droit d'installer en production.
 */
final class Version20260821060112 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute l'index de recherche normalise sur les destinations.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE destination ADD search_text TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE destination DROP search_text');
    }
}
