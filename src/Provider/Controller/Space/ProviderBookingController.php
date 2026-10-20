<?php

declare(strict_types=1);

namespace App\Provider\Controller\Space;

use App\Availability\Repository\AvailabilityRepository;
use App\Booking\Entity\Booking;
use App\Booking\Enum\BookingStatus;
use App\Booking\Presenter\BookingPaymentPresenter;
use App\Booking\Repository\BookingRepository;
use App\Booking\Service\BookingQrCode;
use App\Notification\Enum\NotificationCategory;
use App\Notification\Service\NotificationService;
use App\Payment\Entity\Payment;
use App\Payment\Repository\PaymentRepository;
use App\Payment\Service\InvoiceRenderer;
use App\Payment\Service\PaymentService;
use App\Provider\Service\ProviderSpace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * « Réservations » — maquette profil_reservations_professionnel.jpeg
 * (02/10) : liste filtrable, actions du workflow `booking` (confirmer,
 * démarrer, terminer, annuler) avec notification du client, règlement
 * reçu (le client paie le professionnel directement, 17/10), export CSV et
 * rapport imprimable. Détail « Réservation payée », facture, QR et contrôle
 * de l'entrée le jour J (20/10).
 */
#[IsGranted('ROLE_PROVIDER')]
final class ProviderBookingController extends AbstractProviderSpaceController
{
    private const PER_PAGE = 6;

    private const ULID = '[0-7][0-9A-HJKMNP-TV-Z]{25}';

    private const TABS = [
        'toutes' => null,
        'confirmees' => [BookingStatus::Confirmed, BookingStatus::InProgress],
        'en-attente' => [BookingStatus::Pending],
        'terminees' => [BookingStatus::Completed],
        'annulees' => [BookingStatus::Cancelled, BookingStatus::Refunded],
    ];

    /**
     * Notification du client pour « start » ; confirm / complete / cancel
     * sont déjà notifiés par BookingNotificationSubscriber (workflow).
     */
    private const START_NOTICE = ['Activité commencée', 'Votre activité « %s » a commencé. Bonne séance !'];

    public function __construct(
        private readonly ProviderSpace $space,
        private readonly BookingRepository $bookingRepository,
        private readonly NotificationService $notifications,
        private readonly EntityManagerInterface $entityManager,
        private readonly AvailabilityRepository $availabilities,
        private readonly PaymentService $paymentService,
        private readonly PaymentRepository $payments,
        private readonly BookingPaymentPresenter $presenter,
        private readonly InvoiceRenderer $invoices,
        private readonly BookingQrCode $qrCode,
        #[Target('booking')]
        private readonly WorkflowInterface $bookingWorkflow,
    ) {
    }

    #[Route(path: ['fr' => '/pro/reservations', 'en' => '/en/pro/bookings'], name: 'app_pro_bookings')]
    public function index(Request $request): Response
    {
        $provider = $this->currentProvider();
        $all = $this->space->bookings($provider);
        [$from, $to, $days] = self::period($request);
        $prevFrom = $from->modify(sprintf('-%d days', $days));
        $prevTo = $from->modify('-1 second');

        $tab = (string) $request->query->get('onglet', 'toutes');
        if (!\array_key_exists($tab, self::TABS)) {
            $tab = 'toutes';
        }
        $query = trim((string) $request->query->get('q', ''));
        $activity = (string) $request->query->get('activite', '');
        $status = (string) $request->query->get('statut', '');
        $sort = (string) $request->query->get('tri', 'recentes');

        $counts = [];
        foreach (self::TABS as $key => $statuses) {
            $counts[$key] = null === $statuses ? \count($all) : \count(array_filter($all, static fn (Booking $b): bool => \in_array($b->getStatus(), $statuses, true)));
        }

        $rows = array_values(array_filter($all, static function (Booking $b) use ($tab, $query, $activity, $status): bool {
            $statuses = self::TABS[$tab];
            $client = $b->getClient();
            $haystack = $b->getReference().' '.$client?->getFirstName().' '.$client?->getLastName().' '.$client?->getEmail().' '.$b->getService()?->getTitle();

            return (null === $statuses || \in_array($b->getStatus(), $statuses, true))
                && ('' === $status || $b->getStatus()->value === $status)
                && ('' === $activity || (string) $b->getService()?->getId() === $activity)
                && ('' === $query || false !== mb_stripos($haystack, ltrim($query, '#')));
        }));

        usort($rows, match ($sort) {
            'anciennes' => static fn (Booking $a, Booking $b): int => $a->getCreatedAt() <=> $b->getCreatedAt(),
            'date' => static fn (Booking $a, Booking $b): int => ($a->getStartsAt() ?? $a->getCreatedAt()) <=> ($b->getStartsAt() ?? $b->getCreatedAt()),
            'montant' => static fn (Booking $a, Booking $b): int => (float) $b->getTotalPrice() <=> (float) $a->getTotalPrice(),
            default => static fn (Booking $a, Booking $b): int => $b->getCreatedAt() <=> $a->getCreatedAt(),
        });

        $total = \count($rows);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($pages, max(1, $request->query->getInt('page', 1)));

        // Encarts : période choisie vs précédente.
        $current = $this->space->createdBetween($all, $from, $to);
        $previous = $this->space->createdBetween($all, $prevFrom, $prevTo);
        $summary = [];
        foreach (['confirmees' => 'Confirmées', 'en-attente' => 'En attente', 'terminees' => 'Terminées', 'annulees' => 'Annulées'] as $key => $label) {
            $now = \count(array_filter($current, static fn (Booking $b): bool => \in_array($b->getStatus(), self::TABS[$key], true)));
            $before = \count(array_filter($previous, static fn (Booking $b): bool => \in_array($b->getStatus(), self::TABS[$key], true)));
            $summary[$key] = ['label' => $label, 'value' => $now, 'trend' => $this->space->trend($now, $before)];
        }

        $payments = $this->space->payments($provider);
        $paid = $this->space->paidBetween($payments, $from, $to);
        $revenue = $this->space->sum($paid);
        $paymentByBooking = [];
        foreach ($payments as $p) {
            $paymentByBooking[(string) $p->getBooking()?->getId()] = $p;
        }

        $top = [];
        foreach ($current as $b) {
            if (null !== $service = $b->getService()) {
                $key = (string) $service->getId();
                $top[$key] ??= ['service' => $service, 'count' => 0, 'image' => ProviderSpace::cover($service)];
                ++$top[$key]['count'];
            }
        }
        usort($top, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        $services = [];
        foreach ($this->space->services($provider) as $service) {
            $services[(string) $service->getId()] = $service->getTitle();
        }

        return $this->renderSpace('provider/space/bookings.html.twig', 'Réservations', [
            'rows' => array_map(fn (Booking $b): array => ['b' => $b, 'actions' => $this->transitionsFor($b), 'payment' => $paymentByBooking[(string) $b->getId()] ?? null], \array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE)),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'counts' => $counts,
            'tab' => $tab,
            'query' => $query,
            'activity' => $activity,
            'status' => $status,
            'sort' => $sort,
            'services' => $services,
            'statuses' => BookingStatus::cases(),
            'payment_methods' => Payment::METHODS,
            'days' => $days,
            'summary' => $summary,
            'revenue' => $revenue,
            'revenue_trend' => $this->space->trend($revenue, $this->space->sum($this->space->paidBetween($payments, $prevFrom, $prevTo))),
            'revenue_series' => $this->space->daily(array_map(static fn (Payment $p): array => [ProviderSpace::paidOn($p), (float) $p->getAmount()], $paid), $from, $days),
            'from' => $from,
            'to' => $to,
            'top' => \array_slice($top, 0, 3),
        ]);
    }

    #[Route(path: ['fr' => '/pro/reservations/{id}/{transition}', 'en' => '/en/pro/bookings/{id}/{transition}'], name: 'app_pro_bookings_transition', requirements: ['transition' => 'confirm|start|complete|cancel'], methods: ['POST'])]
    public function transition(string $id, string $transition, Request $request): Response
    {
        if (!$this->csrfOk($request)) {
            return $this->redirectToRoute('app_pro_bookings');
        }

        $booking = $this->own($id);

        if (!$this->bookingWorkflow->can($booking, $transition)) {
            $this->addFlash('error', 'Cette action n’est pas possible pour une réservation « '.$booking->getStatus()->label().' ».');

            return $this->back($request, 'app_pro_bookings');
        }

        $this->bookingWorkflow->apply($booking, $transition);
        // Annulation : les places du créneau redeviennent réservables.
        if ('cancel' === $transition && null !== $booking->getStartsAt() && null !== $booking->getService()) {
            $this->availabilities->findOneByServiceAndStart($booking->getService(), $booking->getStartsAt())?->release($booking->getParticipants());
        }
        $this->entityManager->flush();

        if ('start' === $transition && null !== $client = $booking->getClient()) {
            $this->notifications->notify($client, NotificationCategory::Booking, self::START_NOTICE[0], sprintf(self::START_NOTICE[1], $booking->getService()?->getTitle() ?? ''));
        }

        $this->addFlash('success', sprintf('Réservation #%s : %s.', $booking->getReference(), mb_strtolower($booking->getStatus()->label())));

        return $this->back($request, 'app_pro_bookings');
    }

    /**
     * Le client a réglé le professionnel (en ligne sur le compte du pro, par
     * virement, sur place…) : le professionnel le déclare ici, avec le moyen
     * utilisé. TrouveMoi n'a rien encaissé.
     */
    #[Route(path: ['fr' => '/pro/reservations/{id}/reglement', 'en' => '/en/pro/bookings/{id}/payment'], name: 'app_pro_bookings_paid', methods: ['POST'])]
    public function markPaid(string $id, Request $request): Response
    {
        if (!$this->csrfOk($request)) {
            return $this->redirectToRoute('app_pro_bookings');
        }

        $booking = $this->own($id);
        try {
            $this->paymentService->markPaid($booking, (string) $request->request->get('method', ''));
            $this->addFlash('success', sprintf('Réservation #%s : règlement enregistré.', $booking->getReference()));
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->back($request, 'app_pro_bookings');
    }

    /**
     * « Réservation payée » — maquette reservation_confirmee_prestataire
     * (20/10) : transaction, réservation, paiement reçu (directement sur le
     * compte du professionnel, sans commission), facture, QR, suivi.
     */
    #[Route(path: ['fr' => '/pro/reservations/{id}', 'en' => '/en/pro/bookings/{id}'], name: 'app_pro_booking_show', requirements: ['id' => self::ULID], methods: ['GET'])]
    public function show(string $id): Response
    {
        $booking = $this->own($id);

        return $this->renderSpace('provider/space/booking_show.html.twig', 'Revenus & Paiements', $this->presenter->present($booking) + [
            'actions' => $this->transitionsFor($booking),
            'payment_methods' => Payment::METHODS,
        ]);
    }

    #[Route(path: ['fr' => '/pro/reservations/{id}/facture', 'en' => '/en/pro/bookings/{id}/invoice'], name: 'app_pro_booking_invoice', requirements: ['id' => self::ULID], methods: ['GET'])]
    public function invoice(string $id, Request $request): Response
    {
        $booking = $this->own($id);
        $payment = $this->payments->findOneByBooking($booking);

        try {
            if (null === $payment) {
                throw new \InvalidArgumentException('La facture sera disponible une fois la réservation réglée.');
            }

            return $this->invoices->response($payment, $request->query->getBoolean('telecharger'));
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_pro_booking_show', ['id' => $id]);
        }
    }

    #[Route(path: ['fr' => '/pro/reservations/{id}/qr', 'en' => '/en/pro/bookings/{id}/qr'], name: 'app_pro_booking_qr', requirements: ['id' => self::ULID], methods: ['GET'])]
    public function qr(string $id): Response
    {
        $booking = $this->own($id);

        return new Response($this->qrCode->png($booking), Response::HTTP_OK, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, sprintf('reservation-%s-qr.png', $booking->getReference())),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Contrôle le jour J : le QR du client, scanné avec l'appareil photo,
     * ouvre cette page ; le code peut aussi être saisi à la main
     * (« Scanner / vérifier le QR »).
     */
    #[Route(path: ['fr' => '/pro/reservations/controle', 'en' => '/en/pro/bookings/check'], name: 'app_pro_booking_scan', methods: ['GET'], priority: 10)]
    public function scan(Request $request): Response
    {
        $code = strtoupper(ltrim(trim((string) $request->query->get('code', '')), '#'));
        if ('' !== $code) {
            foreach ($this->space->bookings($this->currentProvider()) as $booking) {
                if ($booking->getReference() === $code) {
                    return $this->redirectToRoute('app_pro_booking_check', ['id' => (string) $booking->getId()]);
                }
            }
        }

        return $this->renderSpace('provider/space/booking_check.html.twig', 'Réservations', [
            'code' => $code,
            'not_found' => '' !== $code,
            'check' => null,
        ]);
    }

    #[Route(path: ['fr' => '/pro/reservations/{id}/controle', 'en' => '/en/pro/bookings/{id}/check'], name: 'app_pro_booking_check', requirements: ['id' => self::ULID], methods: ['GET'])]
    public function check(string $id): Response
    {
        $booking = Ulid::isValid($id) ? $this->bookingRepository->find(Ulid::fromString($id)) : null;
        // QR d'une autre activité que les siennes : simplement « non reconnu ».
        if (null === $booking || $booking->getService()?->getProvider() !== $this->currentProvider()) {
            return $this->renderSpace('provider/space/booking_check.html.twig', 'Réservations', ['code' => '', 'not_found' => true, 'check' => null]);
        }

        return $this->renderSpace('provider/space/booking_check.html.twig', 'Réservations', [
            'code' => $booking->getReference(),
            'not_found' => false,
            'check' => $this->presenter->present($booking),
        ]);
    }

    /** Entrée validée : le client s'est présenté avec son QR. */
    #[Route(path: ['fr' => '/pro/reservations/{id}/entree', 'en' => '/en/pro/bookings/{id}/check-in'], name: 'app_pro_booking_checkin', requirements: ['id' => self::ULID], methods: ['POST'])]
    public function checkIn(string $id, Request $request): Response
    {
        $booking = $this->own($id);
        if (!$this->csrfOk($request)) {
            return $this->redirectToRoute('app_pro_booking_check', ['id' => $id]);
        }

        if (!\in_array($booking->getStatus(), [BookingStatus::Confirmed, BookingStatus::InProgress, BookingStatus::Completed], true)) {
            $this->addFlash('error', 'Entrée refusée : réservation « '.$booking->getStatus()->label().' ».');
        } elseif (null !== $booking->getCheckedInAt()) {
            $this->addFlash('info', sprintf('Entrée déjà validée le %s.', $booking->getCheckedInAt()->format('d/m/Y à H:i')));
        } else {
            $booking->setCheckedInAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Réservation #%s : entrée validée (%d pers.).', $booking->getReference(), $booking->getParticipants()));
        }

        return $this->redirectToRoute('app_pro_booking_check', ['id' => $id]);
    }

    #[Route(path: ['fr' => '/pro/reservations/export', 'en' => '/en/pro/bookings/export'], name: 'app_pro_bookings_export', priority: 10)]
    public function export(): Response
    {
        $rows = array_map(static fn (Booking $b): array => [
            '#'.$b->getReference(),
            $b->getCreatedAt()?->format('d/m/Y H:i') ?? '',
            trim($b->getClient()?->getFirstName().' '.$b->getClient()?->getLastName()),
            $b->getClient()?->getEmail() ?? '',
            $b->getClient()?->getPhone() ?? '',
            $b->getService()?->getTitle() ?? '',
            $b->getStartsAt()?->format('d/m/Y H:i') ?? '',
            $b->getParticipants(),
            number_format((float) $b->getTotalPrice(), 2, ',', ''),
            $b->getStatus()->label(),
        ], $this->space->bookings($this->currentProvider()));

        return self::csv('reservations-'.date('Y-m-d').'.csv', ['Référence', 'Réservée le', 'Client', 'E-mail', 'Téléphone', 'Activité', 'Date de la séance', 'Participants', 'Montant (€)', 'Statut'], $rows);
    }

    /** Rapport imprimable (« Télécharger le rapport » → enregistrer en PDF depuis le navigateur). */
    #[Route(path: ['fr' => '/pro/reservations/rapport', 'en' => '/en/pro/bookings/report'], name: 'app_pro_bookings_report', priority: 10)]
    public function report(Request $request): Response
    {
        $provider = $this->currentProvider();
        [$from, $to, $days] = self::period($request, 30);
        $bookings = $this->space->createdBetween($this->space->bookings($provider), $from, $to);
        $paid = $this->space->paidBetween($this->space->payments($provider), $from, $to);

        $byStatus = [];
        foreach (BookingStatus::cases() as $s) {
            $byStatus[$s->label()] = \count(array_filter($bookings, static fn (Booking $b): bool => $b->getStatus() === $s));
        }

        return $this->render('provider/space/bookings_report.html.twig', [
            'provider' => $provider,
            'from' => $from,
            'to' => $to,
            'days' => $days,
            'bookings' => $bookings,
            'by_status' => $byStatus,
            'received' => $this->space->sum($paid),
        ]);
    }

    /** @return list<array{0: string, 1: string}> */
    private function transitionsFor(Booking $booking): array
    {
        $labels = ['confirm' => 'Confirmer', 'start' => 'Marquer « en cours »', 'complete' => 'Marquer terminée', 'cancel' => 'Annuler la réservation'];
        $out = [];
        foreach ($labels as $name => $label) {
            if ($this->bookingWorkflow->can($booking, $name)) {
                $out[] = [$name, $label];
            }
        }

        return $out;
    }

    private function own(string $id): Booking
    {
        $booking = Ulid::isValid($id) ? $this->bookingRepository->find(Ulid::fromString($id)) : null;

        if (null === $booking || $booking->getService()?->getProvider() !== $this->currentProvider()) {
            throw $this->createNotFoundException('Réservation introuvable.');
        }

        return $booking;
    }
}
