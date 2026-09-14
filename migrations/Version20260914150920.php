<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Signalements et journal d'audit (§16.4, §18, §18.1 du CDC). Deux tables
 * neuves, aucune donnée existante à migrer.
 */
final class Version20260914150920 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Signalements (report) et journal des actions sensibles (audit_log).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE audit_log (
              action VARCHAR(255) NOT NULL,
              target_type VARCHAR(60) DEFAULT NULL,
              target_id UUID DEFAULT NULL,
              target_label VARCHAR(180) DEFAULT NULL,
              details TEXT DEFAULT NULL,
              created_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                id UUID NOT NULL,
                actor_id UUID DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_F6E1C0F510DAF24A ON audit_log (actor_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE report (
              subject_type VARCHAR(255) NOT NULL,
              subject_id UUID NOT NULL,
              subject_label VARCHAR(180) NOT NULL,
              reason VARCHAR(255) NOT NULL,
              message TEXT DEFAULT NULL,
              status VARCHAR(255) NOT NULL,
              moderator_note TEXT DEFAULT NULL,
              processed_at TIMESTAMP(0)
              WITH
                TIME ZONE DEFAULT NULL,
                id UUID NOT NULL,
                created_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0)
              WITH
                TIME ZONE NOT NULL,
                reporter_id UUID NOT NULL,
                processed_by_id UUID DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_C42F7784E1CFE6F5 ON report (reporter_id)');
        $this->addSql('CREATE INDEX IDX_C42F77842FFD4FD3 ON report (processed_by_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              audit_log
            ADD
              CONSTRAINT FK_F6E1C0F510DAF24A FOREIGN KEY (actor_id) REFERENCES "user" (id) ON DELETE
            SET
              NULL NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              report
            ADD
              CONSTRAINT FK_C42F7784E1CFE6F5 FOREIGN KEY (reporter_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              report
            ADD
              CONSTRAINT FK_C42F77842FFD4FD3 FOREIGN KEY (processed_by_id) REFERENCES "user" (id) ON DELETE
            SET
              NULL NOT DEFERRABLE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audit_log DROP CONSTRAINT FK_F6E1C0F510DAF24A');
        $this->addSql('ALTER TABLE report DROP CONSTRAINT FK_C42F7784E1CFE6F5');
        $this->addSql('ALTER TABLE report DROP CONSTRAINT FK_C42F77842FFD4FD3');
        $this->addSql('DROP TABLE audit_log');
        $this->addSql('DROP TABLE report');
    }
}
