<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Table `faq_entry` : les questions frequentes, gerees depuis le back-office.
 *
 * Une table distincte de `legal_document`, et non un type de plus dans son
 * enumeration : un texte juridique est versionne et ne se modifie jamais une
 * fois publie, parce qu'il faut pouvoir prouver ce que l'utilisateur a
 * accepte. Une reponse de FAQ se corrige sur place, et une coquille ne doit
 * pas obliger a publier une nouvelle version de la FAQ entiere.
 *
 * L'index porte sur (locale, category, position) : c'est exactement la lecture
 * que fait la page /faq, qui liste une langue, groupee par rubrique, dans
 * l'ordre choisi par l'administrateur.
 */
final class Version20260831103632 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cree la table des questions frequentes, pour la FAQ et le Centre d aide.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE faq_entry (category VARCHAR(30) NOT NULL, locale VARCHAR(5) NOT NULL, question VARCHAR(255) NOT NULL, answer TEXT NOT NULL, position INT DEFAULT 0 NOT NULL, published BOOLEAN DEFAULT true NOT NULL, featured BOOLEAN DEFAULT false NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_faq_entry_listing ON faq_entry (locale, category, position)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE faq_entry');
    }
}
