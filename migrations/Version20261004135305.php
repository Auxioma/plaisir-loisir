<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261004135305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Espace pro : promotions, statistiques de consultation, tickets support, notes clients, date des réservations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE client_note (body TEXT NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, provider_id UUID NOT NULL, client_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_1E213976A53A8AA ON client_note (provider_id)');
        $this->addSql('CREATE INDEX IDX_1E21397619EB6921 ON client_note (client_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_client_note ON client_note (provider_id, client_id)');
        $this->addSql('CREATE TABLE page_view (kind VARCHAR(20) NOT NULL, source VARCHAR(20) NOT NULL, viewed_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, id UUID NOT NULL, provider_id UUID NOT NULL, service_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_7939B754A53A8AA ON page_view (provider_id)');
        $this->addSql('CREATE INDEX IDX_7939B754ED5CA9E6 ON page_view (service_id)');
        $this->addSql('CREATE INDEX IDX_7939B754A53A8AACC45557D ON page_view (provider_id, viewed_at)');
        $this->addSql('CREATE TABLE promotion (title VARCHAR(120) NOT NULL, subtitle VARCHAR(180) DEFAULT NULL, kind VARCHAR(255) NOT NULL, discount_percent INT DEFAULT NULL, starts_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, ends_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, paused BOOLEAN DEFAULT false NOT NULL, views_count INT DEFAULT 0 NOT NULL, clicks_count INT DEFAULT 0 NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, provider_id UUID NOT NULL, service_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_C11D7DD1ED5CA9E6 ON promotion (service_id)');
        $this->addSql('CREATE INDEX IDX_C11D7DD1A53A8AA ON promotion (provider_id)');
        $this->addSql('CREATE TABLE support_ticket (number INT NOT NULL, subject VARCHAR(180) NOT NULL, category VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, phone VARCHAR(30) DEFAULT NULL, last_reply_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, author_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1F5A4D5396901F54 ON support_ticket (number)');
        $this->addSql('CREATE INDEX IDX_1F5A4D53F675F31B ON support_ticket (author_id)');
        $this->addSql('CREATE TABLE support_ticket_message (from_staff BOOLEAN DEFAULT false NOT NULL, body TEXT NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, ticket_id UUID NOT NULL, author_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_73251A5C700047D2 ON support_ticket_message (ticket_id)');
        $this->addSql('CREATE INDEX IDX_73251A5CF675F31B ON support_ticket_message (author_id)');
        $this->addSql('ALTER TABLE client_note ADD CONSTRAINT FK_1E213976A53A8AA FOREIGN KEY (provider_id) REFERENCES provider_profile (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE client_note ADD CONSTRAINT FK_1E21397619EB6921 FOREIGN KEY (client_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE page_view ADD CONSTRAINT FK_7939B754A53A8AA FOREIGN KEY (provider_id) REFERENCES provider_profile (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE page_view ADD CONSTRAINT FK_7939B754ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE promotion ADD CONSTRAINT FK_C11D7DD1A53A8AA FOREIGN KEY (provider_id) REFERENCES provider_profile (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE promotion ADD CONSTRAINT FK_C11D7DD1ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE support_ticket ADD CONSTRAINT FK_1F5A4D53F675F31B FOREIGN KEY (author_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE support_ticket_message ADD CONSTRAINT FK_73251A5C700047D2 FOREIGN KEY (ticket_id) REFERENCES support_ticket (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE support_ticket_message ADD CONSTRAINT FK_73251A5CF675F31B FOREIGN KEY (author_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE booking ADD starts_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE booking ADD participants INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE conversation ADD archived_by_provider BOOLEAN DEFAULT false NOT NULL');
        $this->addSql("ALTER TABLE payment ADD method VARCHAR(20) DEFAULT 'card' NOT NULL");
        $this->addSql('ALTER TABLE provider_profile ADD address VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD cover_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD intervention_zone VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD youtube_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD timezone VARCHAR(64) DEFAULT \'Europe/Paris\' NOT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD availability_label VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD payout_method VARCHAR(20) DEFAULT \'bank\' NOT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD payout_iban VARCHAR(34) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD payout_paypal_email VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD payout_card_last4 VARCHAR(4) DEFAULT NULL');
        $this->addSql('ALTER TABLE review ADD booking_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE review ADD service_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE review ALTER quote_id DROP NOT NULL');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C63301C60 FOREIGN KEY (booking_id) REFERENCES booking (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE review ADD CONSTRAINT FK_794381C6ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_794381C63301C60 ON review (booking_id)');
        $this->addSql('CREATE INDEX IDX_794381C6ED5CA9E6 ON review (service_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client_note DROP CONSTRAINT FK_1E213976A53A8AA');
        $this->addSql('ALTER TABLE client_note DROP CONSTRAINT FK_1E21397619EB6921');
        $this->addSql('ALTER TABLE page_view DROP CONSTRAINT FK_7939B754A53A8AA');
        $this->addSql('ALTER TABLE page_view DROP CONSTRAINT FK_7939B754ED5CA9E6');
        $this->addSql('ALTER TABLE promotion DROP CONSTRAINT FK_C11D7DD1A53A8AA');
        $this->addSql('ALTER TABLE promotion DROP CONSTRAINT FK_C11D7DD1ED5CA9E6');
        $this->addSql('ALTER TABLE support_ticket DROP CONSTRAINT FK_1F5A4D53F675F31B');
        $this->addSql('ALTER TABLE support_ticket_message DROP CONSTRAINT FK_73251A5C700047D2');
        $this->addSql('ALTER TABLE support_ticket_message DROP CONSTRAINT FK_73251A5CF675F31B');
        $this->addSql('DROP TABLE client_note');
        $this->addSql('DROP TABLE page_view');
        $this->addSql('DROP TABLE promotion');
        $this->addSql('DROP TABLE support_ticket');
        $this->addSql('DROP TABLE support_ticket_message');
        $this->addSql('ALTER TABLE booking DROP starts_at');
        $this->addSql('ALTER TABLE booking DROP participants');
        $this->addSql('ALTER TABLE conversation DROP archived_by_provider');
        $this->addSql('ALTER TABLE payment DROP method');
        $this->addSql('ALTER TABLE provider_profile DROP address');
        $this->addSql('ALTER TABLE provider_profile DROP cover_path');
        $this->addSql('ALTER TABLE provider_profile DROP intervention_zone');
        $this->addSql('ALTER TABLE provider_profile DROP youtube_url');
        $this->addSql('ALTER TABLE provider_profile DROP timezone');
        $this->addSql('ALTER TABLE provider_profile DROP availability_label');
        $this->addSql('ALTER TABLE provider_profile DROP payout_method');
        $this->addSql('ALTER TABLE provider_profile DROP payout_iban');
        $this->addSql('ALTER TABLE provider_profile DROP payout_paypal_email');
        $this->addSql('ALTER TABLE provider_profile DROP payout_card_last4');
        $this->addSql('ALTER TABLE review DROP CONSTRAINT FK_794381C63301C60');
        $this->addSql('ALTER TABLE review DROP CONSTRAINT FK_794381C6ED5CA9E6');
        $this->addSql('DROP INDEX UNIQ_794381C63301C60');
        $this->addSql('DROP INDEX IDX_794381C6ED5CA9E6');
        $this->addSql('ALTER TABLE review DROP booking_id');
        $this->addSql('ALTER TABLE review DROP service_id');
        $this->addSql('ALTER TABLE review ALTER quote_id SET NOT NULL');
    }
}
