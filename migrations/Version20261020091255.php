<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Paiement en ligne sur le compte du professionnel (20/10, maquettes
 * parametre_configuration_paiement_prestataire et reservation_confirmee_*) :
 * configuration du pro (paiement en ligne, Stripe Connect, PayPal, espèces),
 * suivi du paiement Stripe / PayPal, numéro de facture, entrée validée par QR.
 */
final class Version20261020091255 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Paiement en ligne direct au professionnel (Stripe Connect, PayPal), facture, contrôle QR';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE booking ADD checked_in_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE payment ADD channel VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE payment ADD external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE payment ADD external_account VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE payment ADD transaction_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE payment ADD card_brand VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE payment ADD card_last4 VARCHAR(4) DEFAULT NULL');
        $this->addSql('ALTER TABLE payment ADD invoice_number VARCHAR(30) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6D28840D2DA68207 ON payment (invoice_number)');
        $this->addSql('CREATE INDEX idx_payment_external_id ON payment (external_id)');
        $this->addSql('ALTER TABLE provider_profile ADD online_payment_enabled BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD accepts_stripe BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD accepts_paypal BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD accepts_cash BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD stripe_account_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD stripe_charges_enabled BOOLEAN DEFAULT false NOT NULL');
        // Un pro qui proposait déjà le règlement sur place le garde coché ; le
        // paiement en ligne, lui, reste à activer par le pro lui-même.
        $this->addSql('UPDATE provider_profile SET accepts_cash = true WHERE payment_on_site IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE booking DROP checked_in_at');
        $this->addSql('DROP INDEX UNIQ_6D28840D2DA68207');
        $this->addSql('DROP INDEX idx_payment_external_id');
        $this->addSql('ALTER TABLE payment DROP channel');
        $this->addSql('ALTER TABLE payment DROP external_id');
        $this->addSql('ALTER TABLE payment DROP external_account');
        $this->addSql('ALTER TABLE payment DROP transaction_id');
        $this->addSql('ALTER TABLE payment DROP card_brand');
        $this->addSql('ALTER TABLE payment DROP card_last4');
        $this->addSql('ALTER TABLE payment DROP invoice_number');
        $this->addSql('ALTER TABLE provider_profile DROP online_payment_enabled');
        $this->addSql('ALTER TABLE provider_profile DROP accepts_stripe');
        $this->addSql('ALTER TABLE provider_profile DROP accepts_paypal');
        $this->addSql('ALTER TABLE provider_profile DROP accepts_cash');
        $this->addSql('ALTER TABLE provider_profile DROP stripe_account_id');
        $this->addSql('ALTER TABLE provider_profile DROP stripe_charges_enabled');
    }
}
