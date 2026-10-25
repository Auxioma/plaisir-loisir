<?php

declare(strict_types=1);

namespace App\User\Service;

/** L'identité externe est connue, mais la possession de l'e-mail reste à prouver. */
final class SocialEmailVerificationRequired extends \RuntimeException
{
}
