<?php

declare(strict_types=1);

namespace App\Booking\Controller;

use App\Availability\Repository\AvailabilityRepository;
use App\Booking\Entity\Booking;
use App\Booking\Enum\BookingStatus;
use App\Booking\Repository\BookingRepository;
use App\Booking\Service\BookingService;
use App\Catalog\Entity\Service;
use App\Catalog\Entity\ServicePackage;
use App\Catalog\Repository\ServiceRepository;
use App\Notification\Enum\NotificationCategory;
use App\Notification\Service\NotificationService;
use App\Payment\Repository\PaymentRepository;
use App\Payment\Service\BookingCheckout;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Uid\Ulid;

/**
 * Tunnel de réservation d'une activité (03/10) : panneau de la fiche
 * (date, heure, voyageurs) → récapitulatif → paiement → confirmation.
 *
 * Les dates et heures proposées viennent des créneaux (Availability) ouverts
 * par le professionnel ; sans créneau à venir, le client choisit librement
 * une date et une heure parmi les horaires d'ouverture, et le professionnel
 * confirme depuis son espace.
 */
final class BookingController extends AbstractController
{
    use TargetPathTrait;

    /** Horaires proposés quand l'activité n'a pas de créneau ouvert. */
    public const DEFAULT_TIMES = ['09:00', '10:00', '11:00', '14:00', '15:00', '16:00', '17:00'];

    public const SESSION_KEY = 'booking_draft';

    public function __construct(
        private readonly ServiceRepository $services,
        private readonly AvailabilityRepository $availabilities,
        private readonly BookingRepository $bookings,
        private readonly BookingService $bookingService,
        private readonly BookingCheckout $checkout,
        private readonly PaymentRepository $payments,
        private readonly NotificationService $notifications,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(path: ['fr' => '/activites/{slug}/reserver', 'en' => '/en/activities/{slug}/book'], name: 'app_booking_create', methods: ['POST'])]
    public function create(string $slug, Request $request): Response
    {
        $service = $this->services->findPublishedBySlug($slug);
        if (null === $service) {
            throw $this->createNotFoundException('Activité introuvable.');
        }

        $back = $this->generateUrl('app_activity_show', ['slug' => $slug]);
        $draft = [
            'date' => (string) $request->request->get('date', ''),
            'time' => (string) $request->request->get('time', ''),
            'adults' => max(0, $request->request->getInt('adults', 1)),
            'children' => max(0, $request->request->getInt('children', 0)),
            'package' => (string) $request->request->get('formule', ''),
        ];

        // Visiteur : on garde sa sélection, il se connecte, puis retrouve la
        // fiche pré-remplie pour valider.
        $user = $this->getUser();
        if (!$user instanceof User) {
            $request->getSession()->set(self::SESSION_KEY, $draft + ['slug' => $slug]);
            $this->saveTargetPath($request->getSession(), 'main', $back.'#reserver');
            $this->addFlash('info', 'Connectez-vous ou créez un compte pour finaliser votre réservation : votre sélection est conservée.');

            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('booking', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return $this->redirect($back.'#reserver');
        }

        $participants = $draft['adults'] + $draft['children'];
        $startsAt = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $draft['date'].' '.$draft['time']);
        // Formule choisie sur la fiche (05/10) ; à défaut, la moins chère.
        $package = $this->chosenPackage($service, $draft['package']) ?? $this->cheapestPackage($service);

        $error = match (true) {
            '' === $draft['date'] => 'Choisissez une date.',
            '' === $draft['time'] => 'Choisissez une heure.',
            false === $startsAt => 'Date ou heure invalide.',
            $participants < 1 => 'Ajoutez au moins un voyageur.',
            null !== $service->getCapacity() && $participants > $service->getCapacity() => sprintf('Cette activité accueille %d personnes au maximum.', $service->getCapacity()),
            null === $package => 'Cette activité n’a pas encore de tarif : contactez l’organisateur.',
            $user === $service->getProvider()?->getUser() => 'Vous ne pouvez pas réserver votre propre activité.',
            default => null,
        };

        if (null === $error) {
            \assert(false !== $startsAt && null !== $package);
            $slot = $this->availabilities->findOneByServiceAndStart($service, $startsAt);
            $hasSlots = [] !== $this->availabilities->findUpcomingByService($service, new \DateTimeImmutable());
            if ($hasSlots && null === $slot) {
                $error = 'Ce créneau n’est pas proposé : choisissez une heure dans la liste.';
            } else {
                try {
                    $booking = $this->bookingService->createBooking($user, $service, $package, $participants, $startsAt, $slot);
                    $request->getSession()->remove(self::SESSION_KEY);

                    return $this->redirectToRoute('app_booking_summary', ['id' => (string) $booking->getId()]);
                } catch (\InvalidArgumentException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        $request->getSession()->set(self::SESSION_KEY, $draft + ['slug' => $slug]);
        $this->addFlash('error', $error);

        return $this->redirect($back.'#reserver');
    }

    #[Route(path: ['fr' => '/reservations/{id}', 'en' => '/en/bookings/{id}'], name: 'app_booking_summary', methods: ['GET'])]
    public function summary(string $id): Response
    {
        $booking = $this->own($id);
        if (BookingStatus::Pending !== $booking->getStatus() || null !== $this->payments->findOneByBooking($booking)) {
            return $this->redirectToRoute('app_booking_confirmation', ['id' => $id]);
        }

        return $this->render('booking/summary.html.twig', [
            'booking' => $booking,
            'service' => $booking->getService(),
            'stripe' => $this->checkout->usesStripe(),
        ]);
    }

    #[Route(path: ['fr' => '/reservations/{id}/payer', 'en' => '/en/bookings/{id}/pay'], name: 'app_booking_pay', methods: ['POST'])]
    public function pay(string $id, Request $request): Response
    {
        $booking = $this->own($id);
        if (!$this->isCsrfTokenValid('booking', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return $this->redirectToRoute('app_booking_summary', ['id' => $id]);
        }

        try {
            $url = $this->checkout->start($booking);
            $this->notifyProvider($booking);

            return $this->redirect($url);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_booking_confirmation', ['id' => $id]);
        }
    }

    #[Route(path: ['fr' => '/reservations/{id}/annuler', 'en' => '/en/bookings/{id}/cancel'], name: 'app_booking_abandon', methods: ['POST'])]
    public function abandon(string $id, Request $request): Response
    {
        $booking = $this->own($id);
        if ($this->isCsrfTokenValid('booking', (string) $request->request->get('_token'))
            && BookingStatus::Pending === $booking->getStatus() && null === $this->payments->findOneByBooking($booking)) {
            $slot = null !== $booking->getStartsAt() && null !== $booking->getService()
                ? $this->availabilities->findOneByServiceAndStart($booking->getService(), $booking->getStartsAt()) : null;
            $slot?->release($booking->getParticipants());
            $booking->setStatus(BookingStatus::Cancelled);
            $this->entityManager->flush();
            $this->addFlash('success', 'Réservation abandonnée : aucune somme n’a été prélevée.');
        }

        return $this->redirectToRoute('app_activity_show', ['slug' => $booking->getService()?->getSlug()]);
    }

    #[Route(path: ['fr' => '/reservations/{id}/confirmation', 'en' => '/en/bookings/{id}/confirmation'], name: 'app_booking_confirmation', methods: ['GET'])]
    public function confirmation(string $id): Response
    {
        $booking = $this->own($id);

        return $this->render('booking/confirmation.html.twig', [
            'booking' => $booking,
            'service' => $booking->getService(),
            'payment' => $this->payments->findOneByBooking($booking),
        ]);
    }

    private function notifyProvider(Booking $booking): void
    {
        $owner = $booking->getService()?->getProvider()?->getUser();
        if (null !== $owner) {
            $this->notifications->notify($owner, NotificationCategory::Booking, 'Nouvelle réservation', sprintf(
                '%s — %s, %d pers.',
                $booking->getService()->getTitle(),
                $booking->getStartsAt()?->format('d/m/Y H:i') ?? 'date à convenir',
                $booking->getParticipants(),
            ));
        }
    }

    private function chosenPackage(Service $service, string $id): ?ServicePackage
    {
        foreach ($service->getPackages() as $package) {
            if ('' !== $id && (string) $package->getId() === $id) {
                return $package;
            }
        }

        return null;
    }

    private function cheapestPackage(Service $service): ?ServicePackage
    {
        $best = null;
        foreach ($service->getPackages() as $package) {
            if (null === $best || (float) $package->getPrice() < (float) $best->getPrice()) {
                $best = $package;
            }
        }

        return $best;
    }

    private function own(string $id): Booking
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $booking = Ulid::isValid($id) ? $this->bookings->find(Ulid::fromString($id)) : null;
        if (null === $booking || $booking->getClient() !== $user) {
            throw $this->createNotFoundException('Réservation introuvable.');
        }

        return $booking;
    }
}
