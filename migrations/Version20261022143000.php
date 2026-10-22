<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rattache le paiement d’un bon cadeau au compte de paiement de son prestataire.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gift_card ADD checkout_channel VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE gift_card ADD checkout_account VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE gift_card ADD redeemed_booking_id UUID DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_gift_card_redeemed_booking ON gift_card (redeemed_booking_id)');
        $this->addSql('ALTER TABLE gift_card ADD CONSTRAINT fk_gift_card_redeemed_booking FOREIGN KEY (redeemed_booking_id) REFERENCES booking (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gift_card DROP checkout_channel');
        $this->addSql('ALTER TABLE gift_card DROP checkout_account');
        $this->addSql('ALTER TABLE gift_card DROP CONSTRAINT fk_gift_card_redeemed_booking');
        $this->addSql('DROP INDEX uniq_gift_card_redeemed_booking');
        $this->addSql('ALTER TABLE gift_card DROP redeemed_booking_id');
    }
}
