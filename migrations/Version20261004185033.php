<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261004185033 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Événements : assistant de création complet, inscriptions et invitations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE event_invitation (email VARCHAR(180) DEFAULT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, event_id UUID NOT NULL, user_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_A9F3B88D71F7E88B ON event_invitation (event_id)');
        $this->addSql('CREATE INDEX IDX_A9F3B88DA76ED395 ON event_invitation (user_id)');
        $this->addSql('CREATE TABLE event_registration (status VARCHAR(20) NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, event_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_8FBBAD5471F7E88B ON event_registration (event_id)');
        $this->addSql('CREATE INDEX IDX_8FBBAD54A76ED395 ON event_registration (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_event_registration ON event_registration (event_id, user_id)');
        $this->addSql('ALTER TABLE event_invitation ADD CONSTRAINT FK_A9F3B88D71F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE event_invitation ADD CONSTRAINT FK_A9F3B88DA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE event_registration ADD CONSTRAINT FK_8FBBAD5471F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE event_registration ADD CONSTRAINT FK_8FBBAD54A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE event ADD status VARCHAR(20) DEFAULT \'published\' NOT NULL');
        $this->addSql('ALTER TABLE event ADD publish_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD event_type VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD visibility VARCHAR(20) DEFAULT \'public\' NOT NULL');
        $this->addSql('ALTER TABLE event ADD short_description VARCHAR(160) DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD date_mode VARCHAR(20) DEFAULT \'precise\' NOT NULL');
        $this->addSql('ALTER TABLE event ADD recurrence VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD all_day BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE event ADD timezone VARCHAR(64) DEFAULT \'Europe/Paris\' NOT NULL');
        $this->addSql('ALTER TABLE event ADD reminder VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD show_in_calendar BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE event ADD arrival_time VARCHAR(5) DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD location_type VARCHAR(20) DEFAULT \'precise\' NOT NULL');
        $this->addSql('ALTER TABLE event ADD address VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD city VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD postal_code VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD latitude NUMERIC(10, 7) DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD longitude NUMERIC(10, 7) DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD online_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD access_instructions VARCHAR(200) DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD meeting_point VARCHAR(200) DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD gallery JSON DEFAULT \'[]\' NOT NULL');
        $this->addSql('ALTER TABLE event ADD registration_required BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE event ADD capacity INT DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD waitlist BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE event ADD show_participants BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE event ADD allow_guest_invites BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE event ADD comments_enabled BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE event ADD email_updates BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE contact_message ADD attachment_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE TABLE newsletter_subscriber (id UUID NOT NULL, email VARCHAR(180) NOT NULL, locale VARCHAR(5) DEFAULT \'fr\' NOT NULL, source VARCHAR(40) DEFAULT NULL, unsubscribed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_401562C3E7927C74 ON newsletter_subscriber (email)');
        // Bons cadeaux (04/10).
        $this->addSql('CREATE TABLE gift_card (code VARCHAR(20) NOT NULL, label VARCHAR(180) NOT NULL, amount NUMERIC(12, 2) NOT NULL, currency VARCHAR(3) DEFAULT \'EUR\' NOT NULL, buyer_name VARCHAR(200) NOT NULL, buyer_email VARCHAR(180) NOT NULL, buyer_phone VARCHAR(40) DEFAULT NULL, recipient_name VARCHAR(120) NOT NULL, recipient_email VARCHAR(180) DEFAULT NULL, message TEXT DEFAULT NULL, delivery VARCHAR(10) DEFAULT \'email\' NOT NULL, postal_address TEXT DEFAULT NULL, status VARCHAR(12) DEFAULT \'pending\' NOT NULL, checkout_reference VARCHAR(255) DEFAULT NULL, paid_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, expires_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, service_id UUID DEFAULT NULL, buyer_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E4696A8E77153098 ON gift_card (code)');
        $this->addSql('CREATE INDEX IDX_E4696A8EED5CA9E6 ON gift_card (service_id)');
        $this->addSql('CREATE INDEX IDX_E4696A8E6C755722 ON gift_card (buyer_id)');
        $this->addSql('CREATE INDEX IDX_E4696A8EF73951A6 ON gift_card (checkout_reference)');
        $this->addSql('ALTER TABLE gift_card ADD CONSTRAINT FK_E4696A8EED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE gift_card ADD CONSTRAINT FK_E4696A8E6C755722 FOREIGN KEY (buyer_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_invitation DROP CONSTRAINT FK_A9F3B88D71F7E88B');
        $this->addSql('ALTER TABLE event_invitation DROP CONSTRAINT FK_A9F3B88DA76ED395');
        $this->addSql('ALTER TABLE event_registration DROP CONSTRAINT FK_8FBBAD5471F7E88B');
        $this->addSql('ALTER TABLE event_registration DROP CONSTRAINT FK_8FBBAD54A76ED395');
        $this->addSql('DROP TABLE event_invitation');
        $this->addSql('DROP TABLE event_registration');
        $this->addSql('ALTER TABLE event DROP status');
        $this->addSql('ALTER TABLE event DROP publish_at');
        $this->addSql('ALTER TABLE event DROP event_type');
        $this->addSql('ALTER TABLE event DROP visibility');
        $this->addSql('ALTER TABLE event DROP short_description');
        $this->addSql('ALTER TABLE event DROP date_mode');
        $this->addSql('ALTER TABLE event DROP recurrence');
        $this->addSql('ALTER TABLE event DROP all_day');
        $this->addSql('ALTER TABLE event DROP timezone');
        $this->addSql('ALTER TABLE event DROP reminder');
        $this->addSql('ALTER TABLE event DROP show_in_calendar');
        $this->addSql('ALTER TABLE event DROP arrival_time');
        $this->addSql('ALTER TABLE event DROP location_type');
        $this->addSql('ALTER TABLE event DROP address');
        $this->addSql('ALTER TABLE event DROP city');
        $this->addSql('ALTER TABLE event DROP postal_code');
        $this->addSql('ALTER TABLE event DROP latitude');
        $this->addSql('ALTER TABLE event DROP longitude');
        $this->addSql('ALTER TABLE event DROP online_url');
        $this->addSql('ALTER TABLE event DROP access_instructions');
        $this->addSql('ALTER TABLE event DROP meeting_point');
        $this->addSql('ALTER TABLE event DROP gallery');
        $this->addSql('ALTER TABLE event DROP registration_required');
        $this->addSql('ALTER TABLE event DROP capacity');
        $this->addSql('ALTER TABLE event DROP waitlist');
        $this->addSql('ALTER TABLE event DROP show_participants');
        $this->addSql('ALTER TABLE event DROP allow_guest_invites');
        $this->addSql('ALTER TABLE event DROP comments_enabled');
        $this->addSql('ALTER TABLE event DROP email_updates');
        $this->addSql('ALTER TABLE contact_message DROP attachment_path');
        $this->addSql('DROP TABLE newsletter_subscriber');
        $this->addSql('DROP TABLE gift_card');
    }
}
