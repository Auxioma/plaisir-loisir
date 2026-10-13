<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adresse de contact officielle (13/10, demande du client) :
 * contact@trouvemoi.com, à la place de contact@trouvemoi.fr / .eu, dans les
 * coordonnées saisies au back-office et dans les textes juridiques publiés.
 */
final class Version20261013100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adresse de contact : contact@trouvemoi.com';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE company_contact SET email = 'contact@trouvemoi.com' WHERE email IN ('contact@trouvemoi.fr', 'contact@trouvemoi.eu')");
        $this->addSql("UPDATE legal_document SET content = REPLACE(REPLACE(content, 'contact@trouvemoi.fr', 'contact@trouvemoi.com'), 'contact@trouvemoi.eu', 'contact@trouvemoi.com') WHERE content LIKE '%contact@trouvemoi.fr%' OR content LIKE '%contact@trouvemoi.eu%'");
    }

    public function down(Schema $schema): void
    {
        // Pas de retour : l'ancienne adresse n'est plus valable.
    }
}
