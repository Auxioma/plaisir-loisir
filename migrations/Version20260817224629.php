<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Réinitialisation du mot de passe : stockage du code à usage unique.
 *
 * Trois colonnes ajoutées à « user », toutes additives — aucune donnée
 * existante n'est touchée, et la migration inverse les retire proprement.
 * Le code lui-même n'est jamais stocké : seule son empreinte l'est.
 */
final class Version20260817224629 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le code de réinitialisation du mot de passe (empreinte, échéance, tentatives) à la table user.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD reset_code_hash VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD reset_code_expires_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD reset_code_attempts SMALLINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP reset_code_hash');
        $this->addSql('ALTER TABLE "user" DROP reset_code_expires_at');
        $this->addSql('ALTER TABLE "user" DROP reset_code_attempts');
    }
}
