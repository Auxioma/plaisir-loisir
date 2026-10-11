<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Retours client du 11/10 : nombre de vues des activités, affiché sur les
 * cartes et utilisé pour le tri « Les plus vues ». Reprend les consultations
 * déjà comptées dans page_view.
 */
final class Version20261011100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Compteur de vues des activités (service.views_count)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service ADD views_count INT DEFAULT 0 NOT NULL');
        $this->addSql("UPDATE service s SET views_count = v.n FROM (SELECT service_id, COUNT(*) AS n FROM page_view WHERE kind = 'activity' AND service_id IS NOT NULL GROUP BY service_id) v WHERE v.service_id = s.id");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service DROP views_count');
    }
}
