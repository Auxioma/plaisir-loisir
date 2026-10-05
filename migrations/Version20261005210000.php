<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Demandes de devis (05/10) : ville, date souhaitée, nombre de personnes et
 * budget, pour que les professionnels puissent chiffrer.
 */
final class Version20261005210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Demandes de devis : ville, date, participants, budget';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service_request ADD city VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE service_request ADD desired_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE service_request ADD participants INT DEFAULT NULL');
        $this->addSql('ALTER TABLE service_request ADD budget NUMERIC(12, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service_request DROP city');
        $this->addSql('ALTER TABLE service_request DROP desired_date');
        $this->addSql('ALTER TABLE service_request DROP participants');
        $this->addSql('ALTER TABLE service_request DROP budget');
    }
}
