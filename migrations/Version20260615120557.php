<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260615120557 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Provider : réseaux sociaux et informations fiscales du ProviderProfile (issus de la maquette).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE provider_profile ADD facebook_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD instagram_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD linkedin_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD website_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD fiscal_status VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD fiscal_address VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD fiscal_country VARCHAR(2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE provider_profile DROP facebook_url');
        $this->addSql('ALTER TABLE provider_profile DROP instagram_url');
        $this->addSql('ALTER TABLE provider_profile DROP linkedin_url');
        $this->addSql('ALTER TABLE provider_profile DROP website_url');
        $this->addSql('ALTER TABLE provider_profile DROP fiscal_status');
        $this->addSql('ALTER TABLE provider_profile DROP fiscal_address');
        $this->addSql('ALTER TABLE provider_profile DROP fiscal_country');
    }
}
