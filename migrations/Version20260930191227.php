<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Profil complémentaire de l'utilisateur (écran « Informations
 * personnelles », maquette profil_infos_particulier du 30/09).
 */
final class Version20260930191227 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Utilisateur : date de naissance, genre, langue préférée, pays de résidence';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD birth_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD gender VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD preferred_locale VARCHAR(5) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD country VARCHAR(2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP birth_date');
        $this->addSql('ALTER TABLE "user" DROP gender');
        $this->addSql('ALTER TABLE "user" DROP preferred_locale');
        $this->addSql('ALTER TABLE "user" DROP country');
    }
}
