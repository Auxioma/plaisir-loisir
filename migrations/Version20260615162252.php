<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260615162252 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Avis : table review (avis client adossé à une réservation, un avis par réservation).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE review (rating INT NOT NULL, comment TEXT DEFAULT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, author_id UUID NOT NULL, service_id UUID NOT NULL, booking_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_794381C6F675F31B ON review (author_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_794381C63301C60 ON review (booking_id)');
        $this->addSql('CREATE INDEX IDX_794381C6ED5CA9E6 ON review (service_id)');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C6F675F31B FOREIGN KEY (author_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C6ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C63301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE review DROP CONSTRAINT FK_794381C6F675F31B');
        $this->addSql('ALTER TABLE review DROP CONSTRAINT FK_794381C6ED5CA9E6');
        $this->addSql('ALTER TABLE review DROP CONSTRAINT FK_794381C63301C60');
        $this->addSql('DROP TABLE review');
    }
}
