<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Inscription professionnelle : pieces justificatives et activite principale.
 *
 * DEUX CHANGEMENTS, TIRES DE LA MAQUETTE PROFESSIONNELLE
 *
 * 1. `provider_document` — les pieces deposees a l'etape 2/2 (licence
 *    d'exploitation, certificat de securite alimentaire, documents libres).
 *    La table ne porte QUE des metadonnees : le fichier lui-meme est range
 *    dans var/uploads/provider-documents/, hors racine web. Un extrait Kbis
 *    ou un certificat d'assurance servis depuis public/ seraient
 *    telechargeables par quiconque devine leur adresse.
 *    ON DELETE CASCADE : supprimer un dossier prestataire doit emporter ses
 *    pieces, sinon la table se remplit de lignes qui ne pointent plus nulle
 *    part.
 *
 * 2. `provider_profile.main_category_id` — le « Choix de l'activite » de
 *    l'etape 1/2. ON DELETE SET NULL : supprimer une rubrique du catalogue ne
 *    doit pas emporter les dossiers des prestataires qui s'y etaient ranges.
 */
final class Version20260902081500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cree provider_document et ajoute l activite principale au profil prestataire.';
    }

    public function up(Schema $schema): void
    {
        // Noms de contrainte et d'index laisses a Doctrine (FK_/IDX_ + empreinte) :
        // ce sont ceux que `doctrine:schema:update --dump-sql` attend. Les
        // renommer ferait apparaitre un ecart permanent entre le schema et la base.
        $this->addSql('CREATE TABLE provider_document (kind VARCHAR(40) NOT NULL, original_name VARCHAR(255) NOT NULL, stored_name VARCHAR(120) NOT NULL, mime_type VARCHAR(100) NOT NULL, size_bytes INT NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, provider_profile_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_provider_document_profile ON provider_document (provider_profile_id)');
        $this->addSql('ALTER TABLE provider_document ADD CONSTRAINT FK_902BE49FE94C5E00 FOREIGN KEY (provider_profile_id) REFERENCES provider_profile (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql('ALTER TABLE provider_profile ADD main_category_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD CONSTRAINT FK_710F08A8C6C55574 FOREIGN KEY (main_category_id) REFERENCES category (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_710F08A8C6C55574 ON provider_profile (main_category_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE provider_profile DROP CONSTRAINT FK_710F08A8C6C55574');
        $this->addSql('DROP INDEX IDX_710F08A8C6C55574');
        $this->addSql('ALTER TABLE provider_profile DROP main_category_id');
        $this->addSql('DROP TABLE provider_document');
    }
}
