<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Encaissement direct par le professionnel (17/10, demande du client) : les
 * « moyens de versement » (TrouveMoi reversait au pro) deviennent les moyens
 * de paiement que le pro propose à ses clients ; un règlement n'a plus de
 * moyen tant que le pro ne l'a pas déclaré reçu.
 */
final class Version20261017090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prestations réglées directement au professionnel : moyens de paiement du pro, règlement déclaré';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE provider_profile RENAME COLUMN payout_iban TO payment_iban');
        $this->addSql('ALTER TABLE provider_profile RENAME COLUMN payout_paypal_email TO payment_paypal');
        $this->addSql('ALTER TABLE provider_profile DROP payout_method');
        $this->addSql('ALTER TABLE provider_profile DROP payout_card_last4');
        $this->addSql('ALTER TABLE provider_profile ADD payment_link_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD payment_iban_holder VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD payment_on_site VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD payment_instructions TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE payment ALTER method DROP DEFAULT');
        $this->addSql('ALTER TABLE payment ALTER method DROP NOT NULL');
        // Règlements encore attendus : aucun moyen tant que le pro ne l'a pas reçu.
        $this->addSql("UPDATE payment SET method = NULL WHERE status = 'pending'");
        $this->addSql('ALTER TABLE payment ADD paid_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql("UPDATE payment SET paid_at = created_at WHERE status = 'paid'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment DROP paid_at');
        $this->addSql("UPDATE payment SET method = 'card' WHERE method IS NULL OR method NOT IN ('card', 'paypal', 'transfer')");
        $this->addSql("ALTER TABLE payment ALTER method SET DEFAULT 'card'");
        $this->addSql('ALTER TABLE payment ALTER method SET NOT NULL');
        $this->addSql('ALTER TABLE provider_profile DROP payment_link_url');
        $this->addSql('ALTER TABLE provider_profile DROP payment_iban_holder');
        $this->addSql('ALTER TABLE provider_profile DROP payment_on_site');
        $this->addSql('ALTER TABLE provider_profile DROP payment_instructions');
        $this->addSql("ALTER TABLE provider_profile ADD payout_method VARCHAR(20) DEFAULT 'bank' NOT NULL");
        $this->addSql('ALTER TABLE provider_profile ADD payout_card_last4 VARCHAR(4) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_profile RENAME COLUMN payment_iban TO payout_iban');
        $this->addSql('ALTER TABLE provider_profile RENAME COLUMN payment_paypal TO payout_paypal_email');
    }
}
