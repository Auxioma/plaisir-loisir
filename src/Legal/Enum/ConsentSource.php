<?php

declare(strict_types=1);

namespace App\Legal\Enum;

/**
 * Circonstance dans laquelle un consentement a été recueilli.
 *
 * Le RGPD impose de pouvoir démontrer le consentement (article 7.1) : savoir
 * QUAND et OÙ il a été donné fait partie de la preuve, au même titre que la
 * version du document acceptée.
 */
enum ConsentSource: string
{
    /** Case à cocher du formulaire d'inscription. */
    case Registration = 'registration';

    /** Ré-acceptation demandée après la publication d'une nouvelle version. */
    case DocumentUpdate = 'document_update';

    /** Action volontaire depuis l'espace compte. */
    case AccountSettings = 'account_settings';

    /** Reprise d'un consentement antérieur à la mise en place de ce suivi. */
    case Import = 'import';
}
