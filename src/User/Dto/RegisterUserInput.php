<?php

declare(strict_types=1);

namespace App\User\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Données reçues à l'inscription. Validées par API Platform avant traitement.
 */
final class RegisterUserInput
{
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public string $email = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 8, max: 4096)]
    public string $plainPassword = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $firstName = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $lastName = '';
}
