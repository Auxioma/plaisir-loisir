<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Connexion par Google, Facebook et Apple.
 *
 * Une table de liaison plutôt que des colonnes sur « user » : la même personne
 * peut relier plusieurs fournisseurs à un seul compte.
 *
 * La clé d'unicité porte sur (provider, external_id) et NON sur l'e-mail :
 * l'identifiant que renvoie le fournisseur est stable, l'adresse peut changer
 * ou, chez Apple, être une adresse relais différente selon l'application.
 */
final class Version20260820215209 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table des identites externes (Google, Facebook, Apple).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE social_identity (provider VARCHAR(20) NOT NULL, external_id VARCHAR(255) NOT NULL, external_email VARCHAR(180) DEFAULT NULL, display_name VARCHAR(180) DEFAULT NULL, avatar_url VARCHAR(500) DEFAULT NULL, last_login_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_social_identity_user ON social_identity (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_social_identity_provider_external ON social_identity (provider, external_id)');
        $this->addSql('ALTER TABLE social_identity ADD CONSTRAINT FK_E4A47DE8A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE social_identity DROP CONSTRAINT FK_E4A47DE8A76ED395');
        $this->addSql('DROP TABLE social_identity');
    }
}
