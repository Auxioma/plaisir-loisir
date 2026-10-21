<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Liaison PayPal vérifiée et reprise de l’inscription marchand';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE provider_profile ADD paypal_merchant_id VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD paypal_tracking_id VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD paypal_payments_enabled BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE provider_profile DROP paypal_merchant_id');
        $this->addSql('ALTER TABLE provider_profile DROP paypal_tracking_id');
        $this->addSql('ALTER TABLE provider_profile DROP paypal_payments_enabled');
    }
}
