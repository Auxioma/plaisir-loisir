<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260915193516 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Avis (review) reconnecté au modèle demande/devis : quote_id + provider_id remplacent booking_id/service_id (Lot H, §16.2 du CDC).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE review DROP CONSTRAINT fk_794381c63301c60');
        $this->addSql('ALTER TABLE review DROP CONSTRAINT fk_794381c6ed5ca9e6');
        $this->addSql('DROP INDEX idx_794381c6ed5ca9e6');
        $this->addSql('DROP INDEX uniq_794381c63301c60');
        $this->addSql('ALTER TABLE review ADD provider_id UUID NOT NULL');
        $this->addSql('ALTER TABLE review ADD quote_id UUID NOT NULL');
        $this->addSql('ALTER TABLE review DROP service_id');
        $this->addSql('ALTER TABLE review DROP booking_id');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C6A53A8AA FOREIGN KEY (provider_id) REFERENCES provider_profile (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C6DB805178 FOREIGN KEY (quote_id) REFERENCES quote (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_794381C6DB805178 ON review (quote_id)');
        $this->addSql('CREATE INDEX IDX_794381C6A53A8AA ON review (provider_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE review DROP CONSTRAINT FK_794381C6A53A8AA');
        $this->addSql('ALTER TABLE review DROP CONSTRAINT FK_794381C6DB805178');
        $this->addSql('DROP INDEX UNIQ_794381C6DB805178');
        $this->addSql('DROP INDEX IDX_794381C6A53A8AA');
        $this->addSql('ALTER TABLE review ADD service_id UUID NOT NULL');
        $this->addSql('ALTER TABLE review ADD booking_id UUID NOT NULL');
        $this->addSql('ALTER TABLE review DROP provider_id');
        $this->addSql('ALTER TABLE review DROP quote_id');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT fk_794381c63301c60 FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT fk_794381c6ed5ca9e6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_794381c6ed5ca9e6 ON review (service_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_794381c63301c60 ON review (booking_id)');
    }
}
