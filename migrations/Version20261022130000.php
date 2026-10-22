<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Suivi des e-mails des bons cadeaux pour permettre la reprise après un échec d’envoi.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gift_card ADD buyer_email_sent_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE gift_card ADD recipient_email_sent_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gift_card DROP buyer_email_sent_at');
        $this->addSql('ALTER TABLE gift_card DROP recipient_email_sent_at');
    }
}
