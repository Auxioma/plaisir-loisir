<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Favoris étendus à l'univers Event (01/10) : événements, groupes,
 * activités privées et organisateurs.
 */
final class Version20261001092622 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Favoris : événements, groupes, activités privées et organisateurs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE favorite ADD event_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE favorite ADD group_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE favorite ADD private_activity_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE favorite ADD organizer_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE favorite ADD CONSTRAINT FK_68C58ED971F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE favorite ADD CONSTRAINT FK_68C58ED9FE54D947 FOREIGN KEY (group_id) REFERENCES "group" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE favorite ADD CONSTRAINT FK_68C58ED94FAC41A3 FOREIGN KEY (private_activity_id) REFERENCES private_activity (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE favorite ADD CONSTRAINT FK_68C58ED9876C4DDA FOREIGN KEY (organizer_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_68C58ED971F7E88B ON favorite (event_id)');
        $this->addSql('CREATE INDEX IDX_68C58ED9FE54D947 ON favorite (group_id)');
        $this->addSql('CREATE INDEX IDX_68C58ED94FAC41A3 ON favorite (private_activity_id)');
        $this->addSql('CREATE INDEX IDX_68C58ED9876C4DDA ON favorite (organizer_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_favorite_user_event ON favorite (user_id, event_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_favorite_user_group ON favorite (user_id, group_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_favorite_user_private_activity ON favorite (user_id, private_activity_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_favorite_user_organizer ON favorite (user_id, organizer_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE favorite DROP CONSTRAINT FK_68C58ED971F7E88B');
        $this->addSql('ALTER TABLE favorite DROP CONSTRAINT FK_68C58ED9FE54D947');
        $this->addSql('ALTER TABLE favorite DROP CONSTRAINT FK_68C58ED94FAC41A3');
        $this->addSql('ALTER TABLE favorite DROP CONSTRAINT FK_68C58ED9876C4DDA');
        $this->addSql('DROP INDEX IDX_68C58ED971F7E88B');
        $this->addSql('DROP INDEX IDX_68C58ED9FE54D947');
        $this->addSql('DROP INDEX IDX_68C58ED94FAC41A3');
        $this->addSql('DROP INDEX IDX_68C58ED9876C4DDA');
        $this->addSql('DROP INDEX uniq_favorite_user_event');
        $this->addSql('DROP INDEX uniq_favorite_user_group');
        $this->addSql('DROP INDEX uniq_favorite_user_private_activity');
        $this->addSql('DROP INDEX uniq_favorite_user_organizer');
        $this->addSql('ALTER TABLE favorite DROP event_id');
        $this->addSql('ALTER TABLE favorite DROP group_id');
        $this->addSql('ALTER TABLE favorite DROP private_activity_id');
        $this->addSql('ALTER TABLE favorite DROP organizer_id');
    }
}
