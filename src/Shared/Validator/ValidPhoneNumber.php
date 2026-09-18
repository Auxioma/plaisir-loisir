<?php

declare(strict_types=1);

namespace App\Shared\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Numéro de téléphone valide au sens libphonenumber (format, longueur,
 * plage d'indicatif), pour un pays par défaut donné.
 *
 * Nommée `ValidPhoneNumber` et non `PhoneNumber`, pour ne pas entrer en
 * collision de nom avec `libphonenumber\PhoneNumber` (l'objet numéro parsé).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ValidPhoneNumber extends Constraint
{
    public string $message = 'Veuillez saisir un numéro de téléphone valide.';

    /** Région ISO 3166-1 alpha-2 utilisée quand le numéro est saisi sans indicatif international. */
    public string $defaultRegion = 'FR';

    public function __construct(
        ?string $defaultRegion = null,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct([], $groups, $payload);

        $this->defaultRegion = $defaultRegion ?? $this->defaultRegion;
        $this->message = $message ?? $this->message;
    }
}
