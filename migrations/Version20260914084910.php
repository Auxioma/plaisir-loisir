<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Recherche de professionnels (§5, §9 du CDC) : adresse publique et ville.
 *
 * `slug` reste NULLABLE en base : les profils déjà en base (fixtures,
 * inscriptions passées) n'en ont pas. `ProviderSlugService::assign()` le
 * calcule aux nouvelles inscriptions, et `app:provider:backfill-slugs`
 * rattrape les dossiers existants. L'unicité, elle, est immédiate : PostgreSQL
 * autorise plusieurs NULL dans un index unique, donc aucun conflit tant que
 * le rattrapage n'a pas tourné.
 */
final class Version20260914084910 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute l\'adresse publique (slug) et la ville au profil prestataire.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE provider_profile ADD slug VARCHAR(160) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD city VARCHAR(120) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_710F08A8989D9B62 ON provider_profile (slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_710F08A8989D9B62');
        $this->addSql('ALTER TABLE provider_profile DROP slug');
        $this->addSql('ALTER TABLE provider_profile DROP city');
    }
}
