<?php

declare(strict_types=1);

namespace App\Provider\Service;

use App\Booking\Entity\Booking;
use App\Booking\Enum\BookingStatus;
use App\Booking\Repository\BookingRepository;
use App\Catalog\Entity\Service;
use App\Catalog\Repository\ServiceRepository;
use App\Payment\Entity\Payment;
use App\Payment\Enum\PaymentStatus;
use App\Payment\Repository\PaymentRepository;
use App\Provider\Entity\ProviderProfile;
use App\Review\Entity\Review;
use App\Review\Enum\ReviewStatus;
use App\Review\Repository\ReviewRepository;
use App\Stats\Entity\PageView;
use App\Stats\Repository\PageViewRepository;

/**
 * Lecture des données de l'espace professionnel (maquettes
 * docs/maquettes/profil_professionnel, 02/10) : réservations, paiements,
 * avis, consultations et les calculs partagés par les écrans (séries
 * journalières, tendances, répartitions, montants nets).
 *
 * Les requêtes sont mémorisées par professionnel le temps de la requête
 * HTTP : un même écran les sollicite plusieurs fois.
 */
final class ProviderSpace
{
    /** Commission prélevée par TrouveMoi sur chaque réservation payée. */
    public const COMMISSION_RATE = 0.12;

    /** Frais de paiement (prestataire de paiement), en part du montant. */
    public const PAYMENT_FEES_RATE = 0.018;

    /** Statuts qui comptent comme une réservation « réelle » (hors annulations). */
    public const ACTIVE_STATUSES = [BookingStatus::Pending, BookingStatus::Confirmed, BookingStatus::InProgress, BookingStatus::Completed];

    /** @var array<string, list<Booking>> */
    private array $bookingCache = [];

    /** @var array<string, list<Service>> */
    private array $serviceCache = [];

    /** @var array<string, list<Payment>> */
    private array $paymentCache = [];

    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly PaymentRepository $paymentRepository,
        private readonly ReviewRepository $reviewRepository,
        private readonly PageViewRepository $pageViews,
    ) {
    }

    /** @return list<Booking> */
    public function bookings(ProviderProfile $provider): array
    {
        return $this->bookingCache[(string) $provider->getId()] ??= $this->bookingRepository->findForProvider($provider);
    }

    /** @return list<Service> */
    public function services(ProviderProfile $provider): array
    {
        return $this->serviceCache[(string) $provider->getId()] ??= $this->serviceRepository->findForProvider($provider);
    }

    /** @return list<Payment> */
    public function payments(ProviderProfile $provider): array
    {
        return $this->paymentCache[(string) $provider->getId()] ??= $this->paymentRepository->findForProvider($provider);
    }

    /**
     * Avis visibles (publiés), du plus récent au plus ancien.
     *
     * @return list<Review>
     */
    public function reviews(ProviderProfile $provider): array
    {
        return array_values(array_filter(
            $this->reviewRepository->findForProvider($provider),
            static fn (Review $r): bool => ReviewStatus::Published === $r->getStatus(),
        ));
    }

    /** @return list<PageView> */
    public function views(ProviderProfile $provider, \DateTimeImmutable $from, \DateTimeImmutable $to, ?string $kind = null): array
    {
        return $this->pageViews->findForProvider($provider, $from, $to, $kind);
    }

    public function serviceViews(Service $service, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): int
    {
        return $this->pageViews->countForService($service, $from, $to);
    }

    public function totalProfileViews(ProviderProfile $provider): int
    {
        return $this->pageViews->countForProvider($provider, PageView::KIND_PROFILE);
    }

    /**
     * Réservations dont la date de création tombe dans [from, to].
     *
     * @param list<Booking> $bookings
     *
     * @return list<Booking>
     */
    public function createdBetween(array $bookings, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return array_values(array_filter($bookings, static fn (Booking $b): bool => null !== $b->getCreatedAt() && $b->getCreatedAt() >= $from && $b->getCreatedAt() <= $to));
    }

    /**
     * Paiements encaissés (payés) dans [from, to].
     *
     * @param list<Payment> $payments
     *
     * @return list<Payment>
     */
    public function paidBetween(array $payments, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return array_values(array_filter($payments, static fn (Payment $p): bool => PaymentStatus::Paid === $p->getStatus() && null !== $p->getCreatedAt() && $p->getCreatedAt() >= $from && $p->getCreatedAt() <= $to));
    }

    /** @param list<Payment> $payments */
    public function sum(array $payments): float
    {
        return array_sum(array_map(static fn (Payment $p): float => (float) $p->getAmount(), $payments));
    }

    /** @return array{gross: float, commission: float, fees: float, net: float} */
    public function split(float $gross): array
    {
        $commission = round($gross * self::COMMISSION_RATE, 2);
        $fees = round($gross * self::PAYMENT_FEES_RATE, 2);

        return ['gross' => $gross, 'commission' => $commission, 'fees' => $fees, 'net' => round($gross - $commission - $fees, 2)];
    }

    /**
     * Valeurs cumulées par jour sur `days` jours à partir de `from`.
     *
     * @param iterable<array{0: \DateTimeInterface|null, 1: float|int}> $points couples (date, valeur)
     *
     * @return list<float>
     */
    public function daily(iterable $points, \DateTimeImmutable $from, int $days): array
    {
        $series = array_fill(0, max(1, $days), 0.0);
        $start = $from->setTime(0, 0);

        foreach ($points as [$date, $value]) {
            if (null === $date) {
                continue;
            }
            $index = (int) $start->diff(\DateTimeImmutable::createFromInterface($date)->setTime(0, 0))->format('%r%a');
            if ($index >= 0 && $index < $days) {
                $series[$index] += $value;
            }
        }

        return $series;
    }

    /** Évolution en % entre deux valeurs ; null sans base de comparaison. */
    public function trend(float $current, float $previous): ?int
    {
        if ($previous <= 0.0) {
            return $current > 0.0 ? null : 0;
        }

        return (int) round(($current - $previous) / $previous * 100);
    }

    /**
     * Répartition d'une liste de libellés en parts (top N + « Autres »).
     *
     * @param list<string> $labels
     *
     * @return list<array{label: string, count: int, percent: int}>
     */
    public function breakdown(array $labels, int $limit = 3): array
    {
        $counts = array_count_values($labels);
        arsort($counts);
        $total = array_sum($counts);
        if (0 === $total) {
            return [];
        }

        $rows = [];
        $others = 0;
        foreach ($counts as $label => $count) {
            if (\count($rows) < $limit) {
                $rows[] = ['label' => (string) $label, 'count' => $count, 'percent' => (int) round($count / $total * 100)];
            } else {
                $others += $count;
            }
        }
        if ($others > 0) {
            $rows[] = ['label' => 'Autres', 'count' => $others, 'percent' => (int) round($others / $total * 100)];
        }

        return $rows;
    }

    /**
     * Note moyenne (sur 5) d'une liste d'avis.
     *
     * @param list<Review> $reviews
     */
    public function average(array $reviews): ?float
    {
        if ([] === $reviews) {
            return null;
        }

        return round(array_sum(array_map(static fn (Review $r): int => $r->getRating(), $reviews)) / \count($reviews), 1);
    }

    /**
     * Statistiques par activité : vues, réservations, note.
     *
     * @param list<Booking> $bookings
     * @param list<Review>  $reviews
     *
     * @return array{views: int, bookings: int, rating: float|null}
     */
    public function activityRow(Service $service, array $bookings, array $reviews): array
    {
        $own = array_filter($bookings, static fn (Booking $b): bool => $b->getService() === $service && \in_array($b->getStatus(), self::ACTIVE_STATUSES, true));
        $rated = array_filter($reviews, static fn (Review $r): bool => $r->getService() === $service);

        return [
            'views' => $this->serviceViews($service),
            'bookings' => \count($own),
            'rating' => $this->average(array_values($rated)) ?? (null !== $service->getRatingAverage() && $service->getReviewsCount() > 0 ? (float) $service->getRatingAverage() : null),
        ];
    }

    /** Image de couverture d'une activité (vignette du catalogue). */
    public static function cover(?Service $service): string
    {
        if (null === $service) {
            return 'images/activities/montgolfiere.jpg';
        }

        $fallback = null;
        foreach ($service->getMedia() as $media) {
            if ('cover' === $media->getType()) {
                return $media->getPath();
            }
            $fallback ??= $media->getPath();
        }

        return $fallback ?? 'images/activities/montgolfiere.jpg';
    }

    /** Prix « à partir de » d'une activité (formule la moins chère). */
    public static function price(Service $service): ?float
    {
        $prices = [];
        foreach ($service->getPackages() as $package) {
            $prices[] = (float) $package->getPrice();
        }

        return [] !== $prices ? min($prices) : null;
    }
}
