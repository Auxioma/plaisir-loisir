<?php

declare(strict_types=1);

namespace App\Stats\Service;

use App\Catalog\Entity\Destination;
use App\Catalog\Entity\Service;
use App\Catalog\Enum\ServiceStatus;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\PrivateActivityStatus;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderStatus;
use App\Review\Entity\Review;
use App\Review\Enum\ReviewStatus;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Chiffres RÉELS de la plateforme, pour les pages institutionnelles
 * (Qui sommes-nous, Carrières, Devenir partenaire).
 *
 * Demande du client (07/10) : plus aucun faux chiffre (« +2,5 millions
 * d'utilisateurs », « 4,8/5 »…). Chaque valeur est comptée en base ; une
 * valeur nulle est retirée par le gabarit plutôt qu'affichée « 0 ».
 */
final class PlatformFigures
{
    /** @var array<string, int|float|null>|null */
    private ?array $figures = null;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return array{activities: int, free_activities: int, providers: int, members: int, destinations: int, rating: ?float, reviews: int}
     */
    public function all(): array
    {
        if (null !== $this->figures) {
            /* @var array{activities: int, free_activities: int, providers: int, members: int, destinations: int, rating: ?float, reviews: int} */
            return $this->figures;
        }

        $count = fn (string $dql, array $parameters = []): int => (int) $this->entityManager->createQuery($dql)->setParameters($parameters)->getSingleScalarResult();

        $average = $this->entityManager
            ->createQuery(sprintf('SELECT AVG(r.rating) FROM %s r WHERE r.status = :published', Review::class))
            ->setParameter('published', ReviewStatus::Published)
            ->getSingleScalarResult();

        return $this->figures = [
            'activities' => $count(sprintf('SELECT COUNT(s.id) FROM %s s WHERE s.status = :published AND s.deletedAt IS NULL', Service::class), ['published' => ServiceStatus::Published]),
            'free_activities' => $count(sprintf('SELECT COUNT(a.id) FROM %s a WHERE a.status IN (:open) AND a.scheduledAt >= :now', PrivateActivity::class), ['open' => [PrivateActivityStatus::Open, PrivateActivityStatus::Full], 'now' => new \DateTimeImmutable()]),
            'providers' => $count(sprintf('SELECT COUNT(p.id) FROM %s p WHERE p.status = :verified', ProviderProfile::class), ['verified' => ProviderStatus::Verified]),
            'members' => $count(sprintf('SELECT COUNT(u.id) FROM %s u', User::class)),
            'destinations' => $count(sprintf('SELECT COUNT(d.id) FROM %s d', Destination::class)),
            'rating' => null === $average ? null : round((float) $average, 1),
            'reviews' => $count(sprintf('SELECT COUNT(r.id) FROM %s r WHERE r.status = :published', Review::class), ['published' => ReviewStatus::Published]),
        ];
    }

    /**
     * Bandeau de chiffres prêt à afficher : seules les valeurs non nulles.
     *
     * @param list<array{0: string, 1: string, 2: string, 3: string}> $wanted [clé, icône, ton, libellé]
     *
     * @return list<array{icon: string, tone: string, value: string, label: string}>
     */
    public function band(array $wanted): array
    {
        $figures = $this->all();
        $band = [];

        foreach ($wanted as [$key, $icon, $tone, $label]) {
            $value = $figures[$key] ?? null;
            if (null === $value || 0 === $value || 0.0 === $value) {
                continue;
            }
            $band[] = [
                'icon' => $icon,
                'tone' => $tone,
                'value' => 'rating' === $key ? number_format((float) $value, 1, ',', ' ').'/5' : number_format((int) $value, 0, ',', ' '),
                'label' => $label,
            ];
        }

        return $band;
    }
}
