<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le point de rendez-vous accepte jusqu'à 2 000 caractères dans l'assistant.
 */
final class Version20261022200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Autorise les descriptions longues pour le point de rendez-vous des activités.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service ALTER meeting_point TYPE TEXT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE service ALTER meeting_point TYPE VARCHAR(2000)');
    }
}
