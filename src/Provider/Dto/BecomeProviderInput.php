<?php

declare(strict_types=1);

namespace App\Provider\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Données envoyées par un utilisateur pour devenir prestataire.
 */
final class BecomeProviderInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public string $displayName = '';

    #[Assert\Length(max: 180)]
    public ?string $companyName = null;

    #[Assert\Length(max: 5000)]
    public ?string $bio = null;
}
