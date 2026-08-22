<?php

declare(strict_types=1);

namespace App\User\OAuth;

use App\User\Enum\SocialProvider;

/**
 * Ce qu'un fournisseur d'identité nous apprend sur la personne qui se connecte.
 *
 * Objet immuable : une fois construit à partir de la réponse du fournisseur, il
 * n'est plus modifié. Il fait la frontière entre les particularités de chaque
 * service (« sub » chez Google, « id » chez Facebook, jeton d'identité chez
 * Apple) et le reste de l'application, qui n'a pas à les connaître.
 */
final readonly class SocialUser
{
    public function __construct(
        public SocialProvider $provider,
        public string $externalId,
        public ?string $email = null,
        /**
         * Le fournisseur atteste-t-il avoir vérifié cette adresse ?
         *
         * Déterminant : c'est la seule condition qui autorise à rattacher cette
         * identité à un compte existant portant la même adresse. Sans cette
         * garantie, il suffirait de déclarer l'adresse de quelqu'un d'autre pour
         * prendre la main sur son compte.
         */
        public bool $emailVerified = false,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $avatarUrl = null,
    ) {
    }

    public function fullName(): ?string
    {
        $name = trim(($this->firstName ?? '').' '.($this->lastName ?? ''));

        return '' !== $name ? $name : null;
    }
}
