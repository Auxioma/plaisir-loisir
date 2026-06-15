<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260615132723 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Réservations : création des tables booking et booking_item (domaine Booking).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE booking (status VARCHAR(255) NOT NULL, total_price NUMERIC(12, 2) DEFAULT \'0.00\' NOT NULL, currency VARCHAR(3) DEFAULT \'EUR\' NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, client_id UUID NOT NULL, service_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_E00CEDDE19EB6921 ON booking (client_id)');
        $this->addSql('CREATE INDEX IDX_E00CEDDEED5CA9E6 ON booking (service_id)');
        $this->addSql('CREATE INDEX IDX_E00CEDDE7B00651C ON booking (status)');
        $this->addSql('CREATE TABLE booking_item (label VARCHAR(150) NOT NULL, unit_price NUMERIC(12, 2) NOT NULL, quantity INT DEFAULT 1 NOT NULL, currency VARCHAR(3) DEFAULT \'EUR\' NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, booking_id UUID NOT NULL, service_package_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_78A07503301C60 ON booking_item (booking_id)');
        $this->addSql('CREATE INDEX IDX_78A0750621D924B ON booking_item (service_package_id)');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE19EB6921 FOREIGN KEY (client_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDEED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE booking_item ADD CONSTRAINT FK_78A07503301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE booking_item ADD CONSTRAINT FK_78A0750621D924B FOREIGN KEY (service_package_id) REFERENCES service_package (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking DROP CONSTRAINT FK_E00CEDDE19EB6921');
        $this->addSql('ALTER TABLE booking DROP CONSTRAINT FK_E00CEDDEED5CA9E6');
        $this->addSql('ALTER TABLE booking_item DROP CONSTRAINT FK_78A07503301C60');
        $this->addSql('ALTER TABLE booking_item DROP CONSTRAINT FK_78A0750621D924B');
        $this->addSql('DROP TABLE booking');
        $this->addSql('DROP TABLE booking_item');
    }
}
