<?php

declare(strict_types=1);

namespace App\Provider\Controller\Space;

use App\Availability\Entity\Availability;
use App\Booking\Entity\Booking;
use App\Booking\Enum\BookingStatus;
use App\Catalog\Entity\Service;
use App\Provider\Service\ProviderSpace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

/**
 * « Calendrier » — maquette profil_calendrier_professionnel.jpeg (02/10).
 *
 * Une case = une séance : les réservations regroupées par activité et
 * horaire (places prises / capacité), plus les créneaux ouverts
 * (Availability) encore sans réservation. Vues Mois, Semaine et Jour ;
 * le professionnel ouvre ou ferme des créneaux d'ici.
 */
#[IsGranted('ROLE_PROVIDER')]
final class ProviderCalendarController extends AbstractProviderSpaceController
{
    public function __construct(
        private readonly ProviderSpace $space,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(path: ['fr' => '/pro/calendrier', 'en' => '/en/pro/calendar'], name: 'app_pro_calendar')]
    public function index(Request $request): Response
    {
        $provider = $this->currentProvider();
        $view = (string) $request->query->get('vue', 'mois');
        if (!\in_array($view, ['mois', 'semaine', 'jour'], true)) {
            $view = 'mois';
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->query->get('date', '')) ?: new \DateTimeImmutable('today');

        [$from, $to] = match ($view) {
            'jour' => [$date, $date->setTime(23, 59, 59)],
            'semaine' => [$date->modify('monday this week'), $date->modify('monday this week')->modify('+6 days')->setTime(23, 59, 59)],
            default => [$date->modify('first day of this month')->modify('monday this week'), $date->modify('last day of this month')->modify('sunday this week')->setTime(23, 59, 59)],
        };

        $sessions = $this->sessions($provider, $from, $to);

        $days = [];
        for ($d = $from; $d <= $to; $d = $d->modify('+1 day')) {
            $key = $d->format('Y-m-d');
            $days[$key] = ['date' => $d, 'sessions' => array_values(array_filter($sessions, static fn (array $s): bool => $s['start']->format('Y-m-d') === $key))];
        }

        // Encarts : à venir et aperçu du mois affiché.
        $now = new \DateTimeImmutable();
        $upcoming = array_values(array_filter($this->sessions($provider, $now, $now->modify('+60 days')), static fn (array $s): bool => 'cancelled' !== $s['state']));
        $monthStart = $date->modify('first day of this month')->setTime(0, 0);
        $monthEnd = $date->modify('last day of this month')->setTime(23, 59, 59);
        $monthBookings = array_filter($this->space->bookings($provider), static fn (Booking $b): bool => null !== $b->getStartsAt() && $b->getStartsAt() >= $monthStart && $b->getStartsAt() <= $monthEnd);

        [$prev, $next] = match ($view) {
            'jour' => [$date->modify('-1 day'), $date->modify('+1 day')],
            'semaine' => [$date->modify('-7 days'), $date->modify('+7 days')],
            default => [$date->modify('first day of previous month'), $date->modify('first day of next month')],
        };

        return $this->renderSpace('provider/space/calendar.html.twig', 'Calendrier', [
            'view' => $view,
            'date' => $date,
            'prev' => $prev,
            'next' => $next,
            'days' => $days,
            'upcoming' => \array_slice($upcoming, 0, 4),
            'month' => [
                'total' => \count($monthBookings),
                'confirmed' => \count(array_filter($monthBookings, static fn (Booking $b): bool => \in_array($b->getStatus(), [BookingStatus::Confirmed, BookingStatus::InProgress, BookingStatus::Completed], true))),
                'pending' => \count(array_filter($monthBookings, static fn (Booking $b): bool => BookingStatus::Pending === $b->getStatus())),
                'cancelled' => \count(array_filter($monthBookings, static fn (Booking $b): bool => \in_array($b->getStatus(), [BookingStatus::Cancelled, BookingStatus::Refunded], true))),
            ],
            'services' => array_values(array_filter($this->space->services($provider), static fn (Service $s): bool => !$s->isDeleted())),
            'hours' => range(7, 21),
        ]);
    }

    #[Route(path: ['fr' => '/pro/calendrier/creneau', 'en' => '/en/pro/calendar/slot'], name: 'app_pro_calendar_slot', methods: ['POST'])]
    public function addSlot(Request $request): Response
    {
        if (!$this->csrfOk($request)) {
            return $this->redirectToRoute('app_pro_calendar');
        }

        $serviceId = (string) $request->request->get('service', '');
        $service = null;
        foreach ($this->space->services($this->currentProvider()) as $candidate) {
            if ((string) $candidate->getId() === $serviceId) {
                $service = $candidate;
            }
        }

        $day = (string) $request->request->get('date', '');
        $start = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $day.' '.$request->request->get('start', ''));
        $end = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $day.' '.$request->request->get('end', ''));
        $capacity = $request->request->getInt('capacity', $service?->getCapacity() ?? 1);

        if (null === $service || false === $start || false === $end || $end <= $start || $capacity < 1) {
            $this->addFlash('error', 'Créneau invalide : choisissez une activité, une date et des horaires cohérents.');

            return $this->redirectToRoute('app_pro_calendar', ['date' => $day ?: null]);
        }

        $this->entityManager->persist((new Availability())->setService($service)->setStartsAt($start)->setEndsAt($end)->setCapacity($capacity));
        $this->entityManager->flush();
        $this->addFlash('success', 'Créneau ouvert : vos clients peuvent le réserver.');

        return $this->redirectToRoute('app_pro_calendar', ['date' => $start->format('Y-m-d'), 'vue' => $request->request->get('vue', 'mois')]);
    }

    #[Route(path: ['fr' => '/pro/calendrier/creneau/{id}/supprimer', 'en' => '/en/pro/calendar/slot/{id}/delete'], name: 'app_pro_calendar_slot_delete', methods: ['POST'])]
    public function deleteSlot(string $id, Request $request): Response
    {
        if (!$this->csrfOk($request)) {
            return $this->redirectToRoute('app_pro_calendar');
        }

        $slot = Ulid::isValid($id) ? $this->entityManager->getRepository(Availability::class)->find(Ulid::fromString($id)) : null;
        if (!$slot instanceof Availability || $slot->getService()?->getProvider() !== $this->currentProvider()) {
            throw $this->createNotFoundException();
        }
        if ($slot->getBooked() > 0) {
            $this->addFlash('error', 'Ce créneau a déjà des réservations : annulez-les d’abord.');

            return $this->back($request, 'app_pro_calendar');
        }

        $this->entityManager->remove($slot);
        $this->entityManager->flush();
        $this->addFlash('success', 'Créneau fermé.');

        return $this->back($request, 'app_pro_calendar');
    }

    /**
     * Séances entre deux dates : réservations regroupées par activité et
     * horaire, puis créneaux ouverts sans réservation.
     *
     * @return list<array{service: Service, start: \DateTimeImmutable, taken: int, capacity: int|null, state: string, slot: string|null}>
     */
    private function sessions(\App\Provider\Entity\ProviderProfile $provider, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $groups = [];
        foreach ($this->space->bookings($provider) as $booking) {
            $start = $booking->getStartsAt();
            $service = $booking->getService();
            if (null === $start || null === $service || $start < $from || $start > $to) {
                continue;
            }
            $key = $service->getId().'|'.$start->format('Y-m-d H:i');
            $groups[$key] ??= ['service' => $service, 'start' => $start, 'taken' => 0, 'capacity' => $service->getCapacity(), 'statuses' => [], 'slot' => null];
            $groups[$key]['statuses'][] = $booking->getStatus();
            if (!\in_array($booking->getStatus(), [BookingStatus::Cancelled, BookingStatus::Refunded], true)) {
                $groups[$key]['taken'] += $booking->getParticipants();
            }
        }

        $serviceIds = array_map(static fn (Service $s): string => (string) $s->getId(), $this->space->services($provider));
        if ([] !== $serviceIds) {
            /** @var list<Availability> $slots */
            $slots = $this->entityManager->createQueryBuilder()
                ->select('a', 's')->from(Availability::class, 'a')->innerJoin('a.service', 's')
                ->andWhere('s.provider = :provider')->andWhere('a.startsAt BETWEEN :from AND :to')
                ->setParameter('provider', $provider->getId(), 'ulid')->setParameter('from', $from)->setParameter('to', $to)
                ->getQuery()->getResult();
            foreach ($slots as $slot) {
                $service = $slot->getService();
                \assert(null !== $service);
                $key = $service->getId().'|'.$slot->getStartsAt()->format('Y-m-d H:i');
                if (isset($groups[$key])) {
                    $groups[$key]['capacity'] = $slot->getCapacity();
                    $groups[$key]['slot'] = (string) $slot->getId();
                } else {
                    $groups[$key] = ['service' => $service, 'start' => $slot->getStartsAt(), 'taken' => $slot->getBooked(), 'capacity' => $slot->getCapacity(), 'statuses' => [], 'slot' => (string) $slot->getId()];
                }
            }
        }

        $sessions = [];
        foreach ($groups as $g) {
            $statuses = $g['statuses'];
            $state = match (true) {
                [] === $statuses => 'open',
                [] !== array_filter($statuses, static fn (BookingStatus $s): bool => \in_array($s, [BookingStatus::Confirmed, BookingStatus::InProgress, BookingStatus::Completed], true)) => 'confirmed',
                \in_array(BookingStatus::Pending, $statuses, true) => 'pending',
                default => 'cancelled',
            };
            unset($g['statuses']);
            $sessions[] = $g + ['state' => $state];
        }
        usort($sessions, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);

        return $sessions;
    }
}
