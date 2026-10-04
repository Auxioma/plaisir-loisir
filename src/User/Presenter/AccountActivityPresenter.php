<?php

declare(strict_types=1);

namespace App\User\Presenter;

use App\Booking\Entity\Booking;
use App\Booking\Enum\BookingStatus;
use App\Booking\Repository\BookingRepository;
use App\Catalog\Presenter\ActivityPresenter;
use App\PrivateActivity\Entity\Participation;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\ParticipationStatus;
use App\PrivateActivity\Enum\PrivateActivityStatus;
use App\PrivateActivity\Enum\PrivateActivityVisibility;
use App\PrivateActivity\Repository\AlbumRepository;
use App\PrivateActivity\Repository\ParticipationRepository;
use App\PrivateActivity\Repository\PhotoRepository;
use App\PrivateActivity\Repository\PrivateActivityRepository;
use App\Quote\Entity\ServiceRequest;
use App\Quote\Enum\QuoteStatus;
use App\Quote\Repository\ServiceRequestRepository;
use App\User\Entity\User;

/**
 * Données de l'espace compte particulier (maquettes profil_particulier,
 * 30/09) : réservations, activités créées, séries des graphiques.
 *
 * UNE RÉSERVATION = UN BOOKING, UNE PARTICIPATION OU UN DEVIS ACCEPTÉ
 * Le particulier « réserve » de trois façons : une activité payante du
 * catalogue (Booking), une place dans une activité privée gratuite
 * (Participation), ou une prestation obtenue par devis (demande clôturée). L'écran « Mes réservations » les présente ensemble, sous
 * une seule forme de ligne. Un Booking ne porte pas de date de prestation :
 * sa date de création fait foi.
 *
 * Tout est calculé à la volée depuis les entités, rien n'est stocké.
 */
final class AccountActivityPresenter
{
    private const FALLBACK_IMAGE = 'images/activities/canoe-riviere.jpg';

    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly ParticipationRepository $participations,
        private readonly PrivateActivityRepository $privateActivities,
        private readonly ActivityPresenter $activityPresenter,
        private readonly AlbumRepository $albums,
        private readonly PhotoRepository $photos,
        private readonly ServiceRequestRepository $requests,
    ) {
    }

    /**
     * Albums accessibles : ceux des activités privées organisées, et de
     * celles rejointes avec une place confirmée (même règle que
     * PrivateActivityVoter::VIEW_ALBUM). Un album « public » est celui d'une
     * activité visible de tous ; les autres sont « privés ».
     *
     * @return list<array{activity: PrivateActivity, title: string, photoCount: int, cover: ?string, date: ?\DateTimeImmutable, public: bool, organizer: bool}>
     */
    public function albums(User $user): array
    {
        /** @var array<string, array{0: PrivateActivity, 1: bool}> $eligible */
        $eligible = [];
        foreach ($this->privateActivities->findByOrganizer($user) as $activity) {
            $eligible[(string) $activity->getId()] = [$activity, true];
        }
        foreach ($this->participations->findByParticipant($user) as $participation) {
            $activity = $participation->getPrivateActivity();
            if (null !== $activity && $participation->isAccepted()) {
                $eligible[(string) $activity->getId()] ??= [$activity, false];
            }
        }

        $albums = [];
        foreach ($eligible as [$activity, $organizer]) {
            $album = $this->albums->findOneByActivity($activity);
            $latest = null !== $album ? $this->photos->findLatestForAlbum($album) : null;

            $albums[] = [
                'activity' => $activity,
                'title' => $activity->getTitle(),
                'photoCount' => null !== $album ? $this->photos->countForAlbum($album) : 0,
                'cover' => $latest?->getPath(),
                'date' => $latest?->getCreatedAt() ?? $activity->getScheduledAt() ?? $activity->getCreatedAt(),
                'public' => PrivateActivityVisibility::Public === $activity->getVisibility(),
                'organizer' => $organizer,
            ];
        }

        usort($albums, static fn (array $a, array $b): int => $b['date'] <=> $a['date']);

        return $albums;
    }

    /**
     * Lignes de « Mes réservations », de la plus récente à la plus ancienne.
     *
     * @return list<array{id: string, kind: string, title: string, place: string, image: string, date: ?\DateTimeImmutable, participants: int, amount: ?float, status: string, statusLabel: string, statusNote: string, category: string, route: ?string, routeParams: array<string, string>, createdAt: ?\DateTimeImmutable}>
     */
    public function reservations(User $user): array
    {
        $rows = [];
        $now = new \DateTimeImmutable();

        foreach ($this->bookings->findByClient($user) as $booking) {
            $rows[] = $this->bookingRow($booking, $now);
        }

        foreach ($this->participations->findByParticipant($user) as $participation) {
            $activity = $participation->getPrivateActivity();
            if (null !== $activity) {
                $rows[] = $this->participationRow($participation, $activity, $now);
            }
        }

        foreach ($this->requests->findByClient($user) as $request) {
            if (!$request->isOpen()) {
                $rows[] = $this->requestRow($request);
            }
        }

        usort($rows, static fn (array $a, array $b): int => ($b['date'] ?? $b['createdAt']) <=> ($a['date'] ?? $a['createdAt']));

        return $rows;
    }

    /**
     * Activités privées organisées par l'utilisateur (« Mes activités créées »).
     *
     * @return list<array{id: string, title: string, place: string, image: string, date: ?\DateTimeImmutable, status: string, statusLabel: string, participants: int, capacity: ?int, pending: int, activity: PrivateActivity}>
     */
    public function createdActivities(User $user): array
    {
        $now = new \DateTimeImmutable();
        $rows = [];

        foreach ($this->privateActivities->findByOrganizer($user) as $activity) {
            $accepted = 0;
            $pending = 0;
            foreach ($activity->getParticipations() as $participation) {
                match ($participation->getStatus()) {
                    ParticipationStatus::Accepted => ++$accepted,
                    ParticipationStatus::Pending => ++$pending,
                    default => null,
                };
            }

            $date = $activity->getScheduledAt();
            [$status, $label] = match (true) {
                PrivateActivityStatus::Cancelled === $activity->getStatus() => ['cancelled', 'Annulée'],
                null !== $date && $date < $now => ['past', 'Terminée'],
                PrivateActivityStatus::Full === $activity->getStatus() => ['full', 'Complète'],
                default => ['online', 'En ligne'],
            };

            $rows[] = [
                'id' => (string) $activity->getId(),
                'title' => $activity->getTitle(),
                'place' => (string) ($activity->getCity() ?? ''),
                'image' => $this->imageFor($activity),
                'date' => $date,
                'status' => $status,
                'statusLabel' => $label,
                'participants' => $accepted,
                'capacity' => $activity->getMaxParticipants(),
                'pending' => $pending,
                'activity' => $activity,
            ];
        }

        return $rows;
    }

    /**
     * Nombre d'événements par jour sur les `$days` derniers jours (aujourd'hui
     * inclus), du plus ancien au plus récent.
     *
     * @param iterable<?\DateTimeImmutable> $dates
     *
     * @return list<int>
     */
    public function dailySeries(iterable $dates, int $days = 30): array
    {
        $today = new \DateTimeImmutable('today');
        $start = $today->modify(sprintf('-%d days', $days - 1));
        $series = array_fill(0, $days, 0);

        foreach ($dates as $date) {
            if (null === $date) {
                continue;
            }
            $day = $date->setTime(0, 0);
            if ($day < $start || $day > $today) {
                continue;
            }
            ++$series[(int) $start->diff($day)->days];
        }

        return $series;
    }

    /**
     * Variation en % entre les `$days` derniers jours et les `$days` d'avant.
     * Null quand la période précédente est vide (pas de base de comparaison).
     *
     * @param iterable<?\DateTimeImmutable> $dates
     */
    public function trend(iterable $dates, int $days = 30): ?int
    {
        $now = new \DateTimeImmutable();
        $from = $now->modify(sprintf('-%d days', $days));
        $before = $from->modify(sprintf('-%d days', $days));
        $current = 0;
        $previous = 0;

        foreach ($dates as $date) {
            if (null === $date) {
                continue;
            }
            if ($date >= $from) {
                ++$current;
            } elseif ($date >= $before) {
                ++$previous;
            }
        }

        return 0 === $previous ? null : (int) round(($current - $previous) * 100 / $previous);
    }

    /**
     * Tracé SVG (attribut `d`) d'une courbe de tendance dans une boîte
     * `$width` × `$height`.
     *
     * @param list<int> $series
     */
    public function sparkline(array $series, int $width = 200, int $height = 36): string
    {
        $count = \count($series);
        if ($count < 2) {
            return '';
        }

        $max = max(1, max($series));
        $points = [];
        foreach ($series as $i => $value) {
            $x = round($i * $width / ($count - 1), 1);
            $y = round($height - 3 - ($value / $max) * ($height - 6), 1);
            $points[] = sprintf('%s %s', $x, $y);
        }

        return 'M'.implode(' L', $points);
    }

    /**
     * Répartition des réservations par catégorie, les plus fréquentes
     * d'abord ; au-delà de `$limit`, le reste est cumulé dans « Autres ».
     *
     * @param list<array{category: string}> $rows
     *
     * @return list<array{label: string, count: int, percent: int}>
     */
    public function categoryBreakdown(array $rows, int $limit = 3): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['category']] = ($counts[$row['category']] ?? 0) + 1;
        }
        arsort($counts);

        $total = array_sum($counts);
        if (0 === $total) {
            return [];
        }

        $top = \array_slice($counts, 0, $limit, true);
        $others = $total - array_sum($top);
        if ($others > 0) {
            $top['Autres'] = ($top['Autres'] ?? 0) + $others;
        }

        $result = [];
        foreach ($top as $label => $count) {
            $result[] = ['label' => (string) $label, 'count' => $count, 'percent' => (int) round($count * 100 / $total)];
        }

        return $result;
    }

    /**
     * @return array{id: string, kind: string, title: string, place: string, image: string, date: ?\DateTimeImmutable, participants: int, amount: ?float, status: string, statusLabel: string, statusNote: string, category: string, route: ?string, routeParams: array<string, string>, createdAt: ?\DateTimeImmutable}
     */
    private function bookingRow(Booking $booking, \DateTimeImmutable $now): array
    {
        $service = $booking->getService();
        $card = null !== $service ? $this->activityPresenter->card($service) : null;

        $participants = $booking->getParticipants();
        foreach ($booking->getItems() as $item) {
            $participants = max($participants, $item->getQuantity());
        }

        [$status, $label, $note] = match ($booking->getStatus()) {
            BookingStatus::Pending => ['pending', 'En attente', 'Paiement en cours'],
            BookingStatus::Confirmed, BookingStatus::InProgress => ['upcoming', 'À venir', 'Confirmée'],
            BookingStatus::Completed => ['past', 'Passée', 'Terminée'],
            BookingStatus::Cancelled => ['cancelled', 'Annulée', 'Réservation annulée'],
            BookingStatus::Refunded => ['cancelled', 'Annulée', 'Remboursée'],
        };

        return [
            'id' => (string) $booking->getId(),
            'kind' => 'booking',
            'title' => $service?->getTitle() ?? 'Activité',
            'place' => (string) ($service?->getPlaceLabel() ?? ''),
            'image' => $card['image'] ?? self::FALLBACK_IMAGE,
            'date' => $booking->getStartsAt() ?? $booking->getCreatedAt(),
            'participants' => max(1, $participants),
            'amount' => (float) $booking->getTotalPrice(),
            'status' => $status,
            'statusLabel' => $label,
            'statusNote' => $note,
            'category' => $service?->getCategory()?->getName() ?? 'Autres',
            'route' => null !== $service ? 'app_activity_show' : null,
            'routeParams' => null !== $service ? ['slug' => $service->getSlug()] : [],
            'createdAt' => $booking->getCreatedAt(),
        ];
    }

    /**
     * @return array{id: string, kind: string, title: string, place: string, image: string, date: ?\DateTimeImmutable, participants: int, amount: ?float, status: string, statusLabel: string, statusNote: string, category: string, route: ?string, routeParams: array<string, string>, createdAt: ?\DateTimeImmutable}
     */
    private function participationRow(Participation $participation, PrivateActivity $activity, \DateTimeImmutable $now): array
    {
        $date = $activity->getScheduledAt();
        $isPast = null !== $date && $date < $now;

        [$status, $label, $note] = match ($participation->getStatus()) {
            ParticipationStatus::Cancelled => ['cancelled', 'Annulée', 'Par vous'],
            ParticipationStatus::Refused => ['cancelled', 'Annulée', 'Par l\'organisateur'],
            ParticipationStatus::Pending => ['pending', 'En attente', 'Validation de l\'organisateur'],
            ParticipationStatus::WaitingList => ['pending', 'En attente', 'Liste d\'attente'],
            ParticipationStatus::Accepted => $isPast ? ['past', 'Passée', 'Terminée'] : ['upcoming', 'À venir', 'Confirmée'],
        };
        if (PrivateActivityStatus::Cancelled === $activity->getStatus()) {
            [$status, $label, $note] = ['cancelled', 'Annulée', 'Par l\'organisateur'];
        }

        return [
            'id' => (string) $participation->getId(),
            'kind' => 'participation',
            'title' => $activity->getTitle(),
            'place' => (string) ($activity->getCity() ?? ''),
            'image' => $this->imageFor($activity),
            'date' => $date,
            'participants' => 1,
            'amount' => null,
            'status' => $status,
            'statusLabel' => $label,
            'statusNote' => $note,
            'category' => $activity->getCategory()?->getName() ?? 'Autres',
            'route' => 'app_private_activity_show',
            'routeParams' => ['id' => (string) $activity->getId()],
            'createdAt' => $participation->getCreatedAt(),
        ];
    }

    /**
     * @return array{id: string, kind: string, title: string, place: string, image: string, date: ?\DateTimeImmutable, participants: int, amount: ?float, status: string, statusLabel: string, statusNote: string, category: string, route: ?string, routeParams: array<string, string>, createdAt: ?\DateTimeImmutable}
     */
    private function requestRow(ServiceRequest $request): array
    {
        $accepted = null;
        foreach ($request->getQuotes() as $quote) {
            if (QuoteStatus::Accepted === $quote->getStatus()) {
                $accepted = $quote;
                break;
            }
        }

        return [
            'id' => (string) $request->getId(),
            'kind' => 'quote',
            'title' => $request->getTitle(),
            'place' => $request->getCategory()?->getName() ?? '',
            'image' => self::FALLBACK_IMAGE,
            'date' => $request->getUpdatedAt() ?? $request->getCreatedAt(),
            'participants' => 1,
            'amount' => null !== $accepted ? (float) $accepted->getAmount() : null,
            'status' => null !== $accepted ? 'past' : 'cancelled',
            'statusLabel' => null !== $accepted ? 'Passée' : 'Annulée',
            'statusNote' => null !== $accepted ? 'Devis accepté' : 'Demande clôturée',
            'category' => $request->getCategory()?->getName() ?? 'Autres',
            'route' => 'app_account_requests_show',
            'routeParams' => ['id' => (string) $request->getId()],
            'createdAt' => $request->getCreatedAt(),
        ];
    }

    private function imageFor(PrivateActivity $activity): string
    {
        $service = $activity->getService();
        if (null !== $service) {
            return $this->activityPresenter->card($service)['image'];
        }

        return self::FALLBACK_IMAGE;
    }
}
