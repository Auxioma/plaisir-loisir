<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Socle juridique de la plateforme.
 *
 * Quatre tables :
 *  - legal_document   : les VERSIONS des textes (CGU, CGV, confidentialité,
 *                       mentions légales, cookies). Un texte publié ne se
 *                       modifie pas, on en publie un suivant.
 *  - legal_acceptance : la preuve qu'un membre a accepté une version précise,
 *                       avec date, adresse IP et agent utilisateur (RGPD,
 *                       article 7.1). La clé étrangère est en RESTRICT :
 *                       supprimer une version encore acceptée détruirait la
 *                       preuve, la base doit s'y opposer.
 *  - cookie_consent   : le choix du bandeau, y compris pour un visiteur non
 *                       connecté, valable treize mois (recommandation CNIL).
 *  - company_identity : l'identité légale d'un prestataire (forme juridique,
 *                       SIRET, TVA, siège, représentant légal, assurance).
 *
 * Les trois colonnes « fiscal_* » de provider_profile disparaissent : elles
 * n'étaient lues nulle part, étaient vides en base et ne suffisaient à aucun
 * dossier réel. Leur contenu est repris, en bien plus complet, par
 * company_identity.
 */
final class Version20260820214126 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cree le socle juridique : documents versionnes, preuves de consentement, cookies et identite legale des prestataires.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE company_identity (legal_form VARCHAR(30) NOT NULL, legal_name VARCHAR(180) DEFAULT NULL, trade_name VARCHAR(180) DEFAULT NULL, siren VARCHAR(9) DEFAULT NULL, siret VARCHAR(14) DEFAULT NULL, vat_number VARCHAR(20) DEFAULT NULL, vat_exempt BOOLEAN DEFAULT false NOT NULL, rcs_city VARCHAR(80) DEFAULT NULL, rcs_number VARCHAR(40) DEFAULT NULL, ape_code VARCHAR(6) DEFAULT NULL, share_capital NUMERIC(14, 2) DEFAULT NULL, registered_street VARCHAR(255) DEFAULT NULL, registered_complement VARCHAR(255) DEFAULT NULL, registered_postal_code VARCHAR(20) DEFAULT NULL, registered_city VARCHAR(120) DEFAULT NULL, registered_country VARCHAR(2) DEFAULT NULL, legal_representative_name VARCHAR(180) DEFAULT NULL, legal_representative_role VARCHAR(80) DEFAULT NULL, insurer_name VARCHAR(180) DEFAULT NULL, insurance_policy_number VARCHAR(80) DEFAULT NULL, insurance_expires_at DATE DEFAULT NULL, verified_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, provider_profile_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7202FCA9E94C5E00 ON company_identity (provider_profile_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_company_identity_siret ON company_identity (siret)');
        $this->addSql('CREATE TABLE cookie_consent (visitor_token VARCHAR(64) NOT NULL, accepted_categories JSON NOT NULL, policy_version VARCHAR(20) DEFAULT NULL, decided_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, user_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_68C9E30EA76ED395 ON cookie_consent (user_id)');
        $this->addSql('CREATE INDEX idx_cookie_consent_token ON cookie_consent (visitor_token, decided_at)');
        $this->addSql('CREATE INDEX idx_cookie_consent_user ON cookie_consent (user_id, decided_at)');
        $this->addSql('CREATE TABLE legal_acceptance (accepted_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, source VARCHAR(30) NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, user_id UUID NOT NULL, document_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_9680462FA76ED395 ON legal_acceptance (user_id)');
        $this->addSql('CREATE INDEX IDX_9680462FC33F7837 ON legal_acceptance (document_id)');
        $this->addSql('CREATE INDEX idx_legal_acceptance_user ON legal_acceptance (user_id, accepted_at)');
        $this->addSql('CREATE TABLE legal_document (type VARCHAR(40) NOT NULL, locale VARCHAR(5) NOT NULL, version VARCHAR(20) NOT NULL, title VARCHAR(180) NOT NULL, content TEXT NOT NULL, change_summary TEXT DEFAULT NULL, published_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, effective_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, requires_reacceptance BOOLEAN DEFAULT false NOT NULL, id UUID NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_legal_document_current ON legal_document (type, locale, published_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_legal_document_version ON legal_document (type, locale, version)');
        $this->addSql('ALTER TABLE company_identity ADD CONSTRAINT FK_7202FCA9E94C5E00 FOREIGN KEY (provider_profile_id) REFERENCES provider_profile (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE cookie_consent ADD CONSTRAINT FK_68C9E30EA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE legal_acceptance ADD CONSTRAINT FK_9680462FA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE legal_acceptance ADD CONSTRAINT FK_9680462FC33F7837 FOREIGN KEY (document_id) REFERENCES legal_document (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE provider_profile DROP fiscal_status');
        $this->addSql('ALTER TABLE provider_profile DROP fiscal_address');
        $this->addSql('ALTER TABLE provider_profile DROP fiscal_country');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE company_identity DROP CONSTRAINT FK_7202FCA9E94C5E00');
        $this->addSql('ALTER TABLE cookie_consent DROP CONSTRAINT FK_68C9E30EA76ED395');
        $this->addSql('ALTER TABLE legal_acceptance DROP CONSTRAINT FK_9680462FA76ED395');
        $this->addSql('ALTER TABLE legal_acceptance DROP CONSTRAINT FK_9680462FC33F7837');
        $this->addSql('DROP TABLE company_identity');
        $this->addSql('DROP TABLE cookie_consent');
        $this->addSql('DROP TABLE legal_acceptance');
        $this->addSql('DROP TABLE legal_document');
        $this->addSql('ALTER TABLE provider_profile ADD fiscal_status VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD fiscal_address VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE provider_profile ADD fiscal_country VARCHAR(2) DEFAULT NULL');
    }
}
