<?php

declare(strict_types=1);

namespace App\PrivateActivity\Twig;

use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Service\PrivateActivityImage;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Aides de gabarit des activités gratuites entre membres.
 */
final class PrivateActivityExtension extends AbstractExtension
{
    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('private_activity_cover', PrivateActivityImage::pathFor(...)),
            new TwigFunction('private_activity_accepted', static fn (PrivateActivity $activity): int => $activity->countAccepted()),
            new TwigFunction('countdown_parts', self::countdownParts(...)),
        ];
    }

    /**
     * Temps restant avant $start, pour le premier affichage du compte à
     * rebours (sans JavaScript) ; assets/free-activities.js prend le relais.
     *
     * @return array{d: int, h: int, m: int, s: int, started: bool}
     */
    public static function countdownParts(\DateTimeInterface $start, ?\DateTimeInterface $now = null): array
    {
        $rest = $start->getTimestamp() - ($now ?? new \DateTimeImmutable())->getTimestamp();
        if ($rest <= 0) {
            return ['d' => 0, 'h' => 0, 'm' => 0, 's' => 0, 'started' => true];
        }

        return [
            'd' => intdiv($rest, 86400),
            'h' => intdiv($rest % 86400, 3600),
            'm' => intdiv($rest % 3600, 60),
            's' => $rest % 60,
            'started' => false,
        ];
    }
}
