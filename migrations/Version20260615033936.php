<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260615033936 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE destination (name VARCHAR(120) NOT NULL, slug VARCHAR(140) NOT NULL, country VARCHAR(2) NOT NULL, region VARCHAR(120) DEFAULT NULL, description TEXT DEFAULT NULL, hero_image VARCHAR(255) DEFAULT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3EC63EAA989D9B62 ON destination (slug)');
        $this->addSql('ALTER TABLE service ADD short_description VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD duration_minutes INT DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD capacity INT DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD level VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD languages JSON NOT NULL');
        $this->addSql('ALTER TABLE service ADD included TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD cancellation_policy VARCHAR(255) DEFAULT \'flexible\' NOT NULL');
        $this->addSql('ALTER TABLE service ADD address VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD city VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD postal_code VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD country VARCHAR(2) DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD latitude NUMERIC(10, 7) DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD longitude NUMERIC(10, 7) DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD destination_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE service ADD CONSTRAINT FK_E19D9AD2816C6140 FOREIGN KEY (destination_id) REFERENCES destination (id)');
        $this->addSql('CREATE INDEX IDX_E19D9AD2816C6140 ON service (destination_id)');
        $this->addSql('ALTER TABLE service_package ADD pricing_unit VARCHAR(255) DEFAULT \'per_person\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE destination');
        $this->addSql('ALTER TABLE service DROP CONSTRAINT FK_E19D9AD2816C6140');
        $this->addSql('DROP INDEX IDX_E19D9AD2816C6140');
        $this->addSql('ALTER TABLE service DROP short_description');
        $this->addSql('ALTER TABLE service DROP duration_minutes');
        $this->addSql('ALTER TABLE service DROP capacity');
        $this->addSql('ALTER TABLE service DROP level');
        $this->addSql('ALTER TABLE service DROP languages');
        $this->addSql('ALTER TABLE service DROP included');
        $this->addSql('ALTER TABLE service DROP cancellation_policy');
        $this->addSql('ALTER TABLE service DROP address');
        $this->addSql('ALTER TABLE service DROP city');
        $this->addSql('ALTER TABLE service DROP postal_code');
        $this->addSql('ALTER TABLE service DROP country');
        $this->addSql('ALTER TABLE service DROP latitude');
        $this->addSql('ALTER TABLE service DROP longitude');
        $this->addSql('ALTER TABLE service DROP destination_id');
        $this->addSql('ALTER TABLE service_package DROP pricing_unit');
    }
}
