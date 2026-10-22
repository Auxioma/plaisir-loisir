<?php

declare(strict_types=1);

namespace App\Booking\Controller;

use App\Availability\Repository\AvailabilityRepository;
use App\Booking\Entity\Booking;
use App\Booking\Enum\BookingStatus;
use App\Booking\Presenter\BookingPaymentPresenter;
use App\Booking\Repository\BookingRepository;
use App\Booking\Service\BookingQrCode;
use App\Booking\Service\BookingService;
use App\Catalog\Entity\Service;
use App\Catalog\Entity\ServicePackage;
use App\Catalog\Repository\ServiceRepository;
use App\Notification\Enum\NotificationCategory;
use App\Notification\Service\NotificationService;
use App\Payment\Repository\PaymentRepository;
use App\Payment\Service\BookingCheckoutService;
use App\Payment\Service\InvoiceRenderer;
use App\Provider\Service\ProviderPaymentMethods;
use App\Shared\Service\AccountIdentityPresenter;
use App\User\Entity\User;
use App\User\StaticAccount;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Uid\Ulid;

/**
 * Tunnel de réservation d'une activité (03/10, refait le 20/10 selon la
 * maquette « Parcours du client — Paiement d'une réservation ») : panneau de
 * la fiche (date, heure, voyageurs) → récapitulatif et paiement sur une
 * page unique → Stripe / PayPal ou demande sur place → confirmation.
 *
 * Le client paie DIRECTEMENT le professionnel (17/10) : par carte sur le
 * compte Stripe du professionnel, par PayPal sur son compte PayPal (la
 * réservation est alors confirmée dès le paiement), ou sur place en espèces
 * (la demande part au professionnel, qui confirme puis déclare le règlement).
 * Rien ne transite par TrouveMoi (BookingCheckoutService).
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
        private readonly BookingCheckoutService $checkout,
        private readonly PaymentRepository $payments,
        private readonly ProviderPaymentMethods $paymentMethods,
        private readonly NotificationService $notifications,
        private readonly EntityManagerInterface $entityManager,
        private readonly BookingPaymentPresenter $presenter,
        private readonly InvoiceRenderer $invoices,
        private readonly BookingQrCode $qrCode,
        private readonly AccountIdentityPresenter $identity,
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

        // Visiteur : on garde sa sélection, il se connecte, puis retrouve le
        // récapitulatif après une nouvelle validation de la sélection.
        $user = $this->getUser();
        if (!$user instanceof User) {
            $request->getSession()->set(self::SESSION_KEY, $draft + ['slug' => $slug]);
            $this->saveTargetPath($request->getSession(), 'main', $this->generateUrl('app_booking_resume'));
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

    /** Rejoue la sélection par POST avec le jeton de la session authentifiée. */
    #[Route(path: ['fr' => '/reservations/reprendre', 'en' => '/en/bookings/resume'], name: 'app_booking_resume', methods: ['GET', 'POST'], priority: 10)]
    public function resume(Request $request): Response
    {
        if ($this->isGranted('ROLE_PROVIDER')) {
            return $this->redirectToRoute('app_pro_dashboard');
        }
        $draft = $request->getSession()->get(self::SESSION_KEY);
        if (!\is_array($draft) || !isset($draft['slug'])) {
            $this->addFlash('info', 'Aucune réservation à reprendre. Choisissez une activité pour réserver.');

            return $this->redirectToRoute('app_activities');
        }
        if (null === $this->services->findPublishedBySlug($draft['slug'])) {
            $request->getSession()->remove(self::SESSION_KEY);
            $this->addFlash('error', 'Cette activité n’est plus disponible. Choisissez une autre activité.');

            return $this->redirectToRoute('app_activities');
        }

        if ($request->isMethod('POST')) {
            $request->request->add([
                'date' => $draft['date'], 'time' => $draft['time'],
                'adults' => $draft['adults'], 'children' => $draft['children'],
                'formule' => $draft['package'],
            ]);

            return $this->create($draft['slug'], $request);
        }

        return $this->render('booking/resume.html.twig');
    }

    #[Route(path: ['fr' => '/reservations/{id}', 'en' => '/en/bookings/{id}'], name: 'app_booking_summary', methods: ['GET'])]
    public function summary(string $id, Request $request): Response
    {
        $booking = $this->own($id);
        if ($this->settled($booking)) {
            return $this->redirectToRoute('app_booking_confirmation', ['id' => $id]);
        }

        return $this->checkoutPage($booking, $request);
    }

    /** Accès direct à la page unique de réservation et paiement. */
    #[Route(path: ['fr' => '/reservations/{id}/paiement', 'en' => '/en/bookings/{id}/payment'], name: 'app_booking_payment', methods: ['GET'])]
    public function payment(string $id, Request $request): Response
    {
        $booking = $this->own($id);
        if ($this->settled($booking)) {
            return $this->redirectToRoute('app_booking_confirmation', ['id' => $id]);
        }

        return $this->checkoutPage($booking, $request);
    }

    /** Ancien écran de validation : rejoint la page unique sans étape supplémentaire. */
    #[Route(path: ['fr' => '/reservations/{id}/paiement/validation', 'en' => '/en/bookings/{id}/payment/review'], name: 'app_booking_payment_review', methods: ['GET'])]
    public function review(string $id, Request $request): Response
    {
        $booking = $this->own($id);
        if ($this->settled($booking)) {
            return $this->redirectToRoute('app_booking_confirmation', ['id' => $id]);
        }

        $view = $this->bookingView($booking);
        $method = (string) $request->query->get('moyen', '');
        if (!$this->isOffered($view['payment_methods'], $method)) {
            $this->addFlash('error', 'Choisissez un moyen de paiement.');

            return $this->redirectToRoute('app_booking_payment', ['id' => $id]);
        }

        return $this->redirectToRoute('app_booking_payment', ['id' => $id, 'moyen' => $method]);
    }

    #[Route(path: ['fr' => '/reservations/{id}/payer', 'en' => '/en/bookings/{id}/pay'], name: 'app_booking_pay', methods: ['POST'])]
    public function pay(string $id, Request $request): Response
    {
        $booking = $this->own($id);
        if (!$this->isCsrfTokenValid('booking', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return $this->redirectToRoute('app_booking_payment', ['id' => $id]);
        }

        $method = (string) $request->request->get('moyen', '');
        $offered = $this->bookingView($booking)['payment_methods'];
        if (!$this->isOffered($offered, $method)) {
            $this->addFlash('error', 'Ce moyen de paiement n’est pas proposé par l’organisateur.');

            return $this->redirectToRoute('app_booking_payment', ['id' => $id]);
        }

        try {
            if (ProviderPaymentMethods::ONSITE === $method) {
                $this->checkout->payOnSite($booking);
                $this->notifyProvider($booking);

                return $this->redirectToRoute('app_booking_confirmation', ['id' => $id]);
            }

            // Vers la page sécurisée de Stripe / PayPal, puis retour ici.
            return $this->redirect($this->checkout->start(
                $booking,
                $method,
                $this->generateUrl('app_booking_payment_return', ['id' => $id], UrlGeneratorInterface::ABSOLUTE_URL),
                $this->generateUrl('app_booking_payment_cancel', ['id' => $id, 'moyen' => $method], UrlGeneratorInterface::ABSOLUTE_URL),
            ));
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute($this->settled($booking) ? 'app_booking_confirmation' : 'app_booking_payment', ['id' => $id]);
        }
    }

    /**
     * Retour depuis Stripe / PayPal : le paiement est vérifié auprès d'eux
     * (l'URL de retour seule ne prouve rien), puis la réservation confirmée.
     */
    #[Route(path: ['fr' => '/reservations/{id}/paiement/retour', 'en' => '/en/bookings/{id}/payment/return'], name: 'app_booking_payment_return', methods: ['GET'])]
    public function paymentReturn(string $id, Request $request): Response
    {
        $booking = $this->own($id);
        $token = $request->query->getString('token');

        if ($this->checkout->complete($booking, '' !== $token ? $token : null)) {
            return $this->redirectToRoute('app_booking_confirmation', ['id' => $id]);
        }

        $this->addFlash('error', 'Le paiement n’a pas été finalisé : aucun montant n’a été débité. Vous pouvez réessayer ou choisir un autre moyen.');

        return $this->redirectToRoute($this->settled($booking) ? 'app_booking_confirmation' : 'app_booking_payment', ['id' => $id]);
    }

    #[Route(path: ['fr' => '/reservations/{id}/paiement/annule', 'en' => '/en/bookings/{id}/payment/cancelled'], name: 'app_booking_payment_cancel', methods: ['GET'])]
    public function paymentCancelled(string $id, Request $request): Response
    {
        $this->own($id);
        $this->addFlash('info', 'Paiement annulé : aucun montant n’a été débité.');

        return $this->redirectToRoute('app_booking_payment', ['id' => $id, 'moyen' => (string) $request->query->get('moyen', '')]);
    }

    #[Route(path: ['fr' => '/reservations/{id}/annuler', 'en' => '/en/bookings/{id}/cancel'], name: 'app_booking_abandon', methods: ['POST'])]
    public function abandon(string $id, Request $request): Response
    {
        $booking = $this->own($id);
        if ($this->isCsrfTokenValid('booking', (string) $request->request->get('_token'))
            && BookingStatus::Pending === $booking->getStatus() && !$this->settled($booking)) {
            try {
                $payment = $this->payments->findOneByBooking($booking);
                if (null !== $payment) {
                    $this->checkout->discard($payment);
                }
                $slot = null !== $booking->getStartsAt() && null !== $booking->getService()
                    ? $this->availabilities->findOneByServiceAndStart($booking->getService(), $booking->getStartsAt()) : null;
                $slot?->release($booking->getParticipants());
                $booking->setStatus(BookingStatus::Cancelled);
                $this->entityManager->flush();
                $this->addFlash('success', 'Réservation abandonnée.');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());

                return $this->redirectToRoute('app_booking_confirmation', ['id' => $id]);
            }
        }

        return $this->redirectToRoute('app_activity_show', ['slug' => $booking->getService()?->getSlug()]);
    }

    /**
     * Après paiement : « Paiement effectué avec succès » — maquette
     * reservation_confirmee_client (20/10), dans l'espace compte du client.
     */
    #[Route(path: ['fr' => '/reservations/{id}/confirmation', 'en' => '/en/bookings/{id}/confirmation'], name: 'app_booking_confirmation', methods: ['GET'])]
    public function confirmation(string $id): Response
    {
        $booking = $this->own($id);
        $user = $this->getUser();
        \assert($user instanceof User);

        return $this->render('booking/confirmation.html.twig', $this->presenter->present($booking) + [
            'payment_instructions' => $booking->getService()?->getProvider()?->getPaymentInstructions(),
            'user' => $this->identity->identityFor($user),
            'menu' => StaticAccount::menu(),
            'active' => 'Paiements',
        ]);
    }

    /** Facture / reçu PDF (`?telecharger=1` : téléchargement, sinon affichage). */
    #[Route(path: ['fr' => '/reservations/{id}/facture', 'en' => '/en/bookings/{id}/invoice'], name: 'app_booking_invoice', methods: ['GET'])]
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

            return $this->redirectToRoute('app_booking_confirmation', ['id' => $id]);
        }
    }

    /**
     * Billet à présenter le jour de l'activité (PDF, 20/10) : infos clés de
     * l'activité, facture ou récapitulatif, et QR de vérification — plutôt
     * qu'une image de QR seule.
     */
    #[Route(path: ['fr' => '/reservations/{id}/qr', 'en' => '/en/bookings/{id}/qr'], name: 'app_booking_qr', methods: ['GET'])]
    public function qr(string $id): Response
    {
        $booking = $this->own($id);

        return $this->invoices->ticketResponse($booking, $this->payments->findOneByBooking($booking), $this->qrCode->png($booking));
    }

    private function checkoutPage(Booking $booking, Request $request): Response
    {
        $view = $this->bookingView($booking);
        $selected = $request->query->getString('moyen');
        if (!$this->isOffered($view['payment_methods'], $selected)) {
            $selected = (string) (array_key_first($view['payment_methods']) ?? ProviderPaymentMethods::ONSITE);
        }
        $user = $this->getUser();
        \assert($user instanceof User);

        return $this->render('booking/payment.html.twig', $view + [
            'selected' => $selected,
            'user' => $this->identity->identityFor($user),
            'menu' => StaticAccount::menu(),
            'active' => 'Paiements',
        ]);
    }

    /** @return array<string, mixed> */
    private function bookingView(Booking $booking): array
    {
        $provider = $booking->getService()?->getProvider();

        return [
            'booking' => $booking,
            'service' => $booking->getService(),
            'payment_methods' => null !== $provider ? $this->paymentMethods->offered($provider) : [],
            'payment_instructions' => $provider?->getPaymentInstructions(),
        ];
    }

    /**
     * Moyen accepté pour cette réservation. Organisateur sans moyen
     * configuré : la demande part « sur place », règlement à convenir.
     *
     * @param array<string, mixed> $offered
     */
    private function isOffered(array $offered, string $method): bool
    {
        return isset($offered[$method]) || ([] === $offered && ProviderPaymentMethods::ONSITE === $method);
    }

    /** Plus rien à payer dans le tunnel : réglée, demande sur place envoyée, ou plus en attente. */
    private function settled(Booking $booking): bool
    {
        $payment = $this->payments->findOneByBooking($booking);

        return BookingStatus::Pending !== $booking->getStatus()
            || (null !== $payment && ($payment->isPaid() || !$payment->isOnline()));
    }

    private function notifyProvider(Booking $booking): void
    {
        $owner = $booking->getService()?->getProvider()?->getUser();
        if (null !== $owner) {
            $this->notifications->notify($owner, NotificationCategory::Booking, 'Nouvelle demande de réservation', sprintf(
                '%s — %s, %d pers. À confirmer depuis « Réservations » ; le client vous règle sur place.',
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
