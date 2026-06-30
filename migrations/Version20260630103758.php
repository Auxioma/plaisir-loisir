<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260630103758 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Devis : tables service_request (demande de besoin) et quote (devis d\'un annonceur).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE quote (amount NUMERIC(12, 2) NOT NULL, currency VARCHAR(3) DEFAULT \'EUR\' NOT NULL, message TEXT DEFAULT NULL, status VARCHAR(255) NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, service_request_id UUID NOT NULL, provider_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_6B71CBF4D42F8111 ON quote (service_request_id)');
        $this->addSql('CREATE INDEX IDX_6B71CBF4A53A8AA ON quote (provider_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_quote_request_provider ON quote (service_request_id, provider_id)');
        $this->addSql('CREATE TABLE service_request (title VARCHAR(180) NOT NULL, description TEXT NOT NULL, status VARCHAR(255) NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, client_id UUID NOT NULL, category_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_F413DD0319EB6921 ON service_request (client_id)');
        $this->addSql('CREATE INDEX IDX_F413DD0312469DE2 ON service_request (category_id)');
        $this->addSql('ALTER TABLE quote ADD CONSTRAINT FK_6B71CBF4D42F8111 FOREIGN KEY (service_request_id) REFERENCES service_request (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE quote ADD CONSTRAINT FK_6B71CBF4A53A8AA FOREIGN KEY (provider_id) REFERENCES provider_profile (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE service_request ADD CONSTRAINT FK_F413DD0319EB6921 FOREIGN KEY (client_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE service_request ADD CONSTRAINT FK_F413DD0312469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE quote DROP CONSTRAINT FK_6B71CBF4D42F8111');
        $this->addSql('ALTER TABLE quote DROP CONSTRAINT FK_6B71CBF4A53A8AA');
        $this->addSql('ALTER TABLE service_request DROP CONSTRAINT FK_F413DD0319EB6921');
        $this->addSql('ALTER TABLE service_request DROP CONSTRAINT FK_F413DD0312469DE2');
        $this->addSql('DROP TABLE quote');
        $this->addSql('DROP TABLE service_request');
    }
}
