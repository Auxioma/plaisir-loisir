<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Retours client du 11/10 : une personne = une vue. Table des visiteurs déjà
 * comptés par page (unique_view). Les vues des activités gratuites, ajoutées
 * le jour même et comptées à chaque clic, repartent de zéro.
 */
final class Version20261011140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Visiteurs uniques (unique_view), remise à zéro des vues des activités gratuites';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE unique_view (kind VARCHAR(20) NOT NULL, target_id UUID NOT NULL, visitor VARCHAR(64) NOT NULL, viewed_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_unique_view_visitor ON unique_view (kind, target_id, visitor)');
        $this->addSql('UPDATE private_activity SET views_count = 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE unique_view');
    }
}
