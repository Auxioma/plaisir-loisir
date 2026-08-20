<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Boite de reception des formulaires institutionnels.
 *
 * Deux tables : contact_message et partner_application.
 *
 * Les demandes sont enregistrees en base et pas seulement expediees par
 * e-mail. Les e-mails du projet partent par la file Messenger : sans worker en
 * service, ils attendent. Un message qui ne serait qu'un e-mail serait perdu au
 * premier incident, et personne ne le saurait — ni l'expediteur, a qui on vient
 * d'afficher « message envoye », ni l'equipe.
 */
final class Version20260820225839 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les tables des messages de contact et des candidatures de partenaires.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE contact_message (name VARCHAR(180) NOT NULL, email VARCHAR(180) NOT NULL, subject VARCHAR(200) NOT NULL, message TEXT NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, handled_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_contact_message_handled ON contact_message (handled_at, created_at)');
        $this->addSql('CREATE TABLE partner_application (site_name VARCHAR(180) NOT NULL, site_url VARCHAR(255) NOT NULL, sector VARCHAR(120) NOT NULL, traffic VARCHAR(60) NOT NULL, company_name VARCHAR(180) DEFAULT NULL, contact_name VARCHAR(180) DEFAULT NULL, phone VARCHAR(30) DEFAULT NULL, city VARCHAR(120) DEFAULT NULL, address VARCHAR(255) NOT NULL, postal_code VARCHAR(20) NOT NULL, email VARCHAR(180) NOT NULL, description TEXT DEFAULT NULL, terms_accepted BOOLEAN DEFAULT false NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, handled_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_partner_application_handled ON partner_application (handled_at, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE contact_message');
        $this->addSql('DROP TABLE partner_application');
    }
}
