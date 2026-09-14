<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Abonnements professionnels (§17, §23 du CDC) : seul modèle de revenu que le
 * CDC autorise (§1.2, §3.2). Deux tables neuves, aucune donnée existante à
 * migrer.
 */
final class Version20260914144514 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Abonnements professionnels : offres (subscription_plan) et souscriptions (subscription).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE subscription (
              status VARCHAR(255) NOT NULL,
              stripe_customer_id VARCHAR(80) DEFAULT NULL,
              stripe_subscription_id VARCHAR(80) DEFAULT NULL,
              current_period_start TIMESTAMP(0)
              WITH
                TIME ZONE DEFAULT NULL,
                current_period_end TIMESTAMP(0)
              WITH
                TIME ZONE DEFAULT NULL,
                cancel_at_period_end BOOLEAN DEFAULT false NOT NULL,
                id UUID NOT NULL,
                created_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                provider_id UUID NOT NULL,
                plan_id UUID NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_A3C664D3A53A8AA ON subscription (provider_id)');
        $this->addSql('CREATE INDEX IDX_A3C664D3E899029B ON subscription (plan_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE subscription_plan (
              name VARCHAR(120) NOT NULL,
              slug VARCHAR(140) NOT NULL,
              description TEXT DEFAULT NULL,
              billing_period VARCHAR(255) NOT NULL,
              price_amount NUMERIC(12, 2) NOT NULL,
              currency VARCHAR(3) DEFAULT 'EUR' NOT NULL,
              max_requests_per_month INT DEFAULT NULL,
              max_responses_per_month INT DEFAULT NULL,
              max_categories INT DEFAULT NULL,
              featured BOOLEAN DEFAULT false NOT NULL,
              advanced_statistics BOOLEAN DEFAULT false NOT NULL,
              stripe_price_id VARCHAR(80) DEFAULT NULL,
              active BOOLEAN DEFAULT true NOT NULL,
              position INT DEFAULT 0 NOT NULL,
              id UUID NOT NULL,
              created_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EA664B63989D9B62 ON subscription_plan (slug)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              subscription
            ADD
              CONSTRAINT FK_A3C664D3A53A8AA FOREIGN KEY (provider_id) REFERENCES provider_profile (id) ON DELETE CASCADE NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              subscription
            ADD
              CONSTRAINT FK_A3C664D3E899029B FOREIGN KEY (plan_id) REFERENCES subscription_plan (id) ON DELETE RESTRICT NOT DEFERRABLE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription DROP CONSTRAINT FK_A3C664D3A53A8AA');
        $this->addSql('ALTER TABLE subscription DROP CONSTRAINT FK_A3C664D3E899029B');
        $this->addSql('DROP TABLE subscription');
        $this->addSql('DROP TABLE subscription_plan');
    }
}
