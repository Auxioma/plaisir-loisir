<?php

declare(strict_types=1);

namespace App\Provider\Controller;

use App\Booking\Entity\Booking;
use App\Catalog\Enum\ServiceStatus;
use App\Catalog\Repository\CategoryRepository;
use App\Notification\Presenter\NotificationPresenter;
use App\Notification\Repository\NotificationRepository;
use App\Payment\Entity\Payment;
use App\Provider\Controller\Space\AbstractProviderSpaceController;
use App\Provider\Service\ProviderSpace;
use App\Quote\Repository\ServiceRequestRepository;
use App\Stats\Entity\PageView;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Tableau de bord professionnel — maquette docs/maquettes/
 * profil_professionnel/profil_dashboard_professionnel.jpeg (02/10).
 *
 * Tout est réel : réservations et revenus (Booking, Payment), activités en
 * ligne, note moyenne (Review), vues de la fiche (PageView), notifications.
 * Les tendances comparent la période choisie à la précédente, de même durée.
 */
#[IsGranted('ROLE_PROVIDER')]
final class ProviderDashboardController extends AbstractProviderSpaceController
{
    public function __construct(
        private readonly ProviderSpace $space,
        private readonly NotificationRepository $notifications,
        private readonly NotificationPresenter $notificationPresenter,
        private readonly ServiceRequestRepository $requests,
        private readonly CategoryRepository $categories,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(path: ['fr' => '/pro/tableau-de-bord', 'en' => '/en/pro/dashboard'], name: 'app_pro_dashboard')]
    public function dashboard(Request $request): Response
    {
        $provider = $this->currentProvider();
        [$from, $to, $days] = self::period($request);
        $prevFrom = $from->modify(sprintf('-%d days', $days));
        $prevTo = $from->modify('-1 second');

        $bookings = $this->space->bookings($provider);
        $active = array_values(array_filter($bookings, static fn (Booking $b): bool => \in_array($b->getStatus(), ProviderSpace::ACTIVE_STATUSES, true)));
        $current = $this->space->createdBetween($active, $from, $to);
        $previous = $this->space->createdBetween($active, $prevFrom, $prevTo);

        $payments = $this->space->payments($provider);
        $revenue = $this->space->sum($this->space->paidBetween($payments, $from, $to));
        $prevRevenue = $this->space->sum($this->space->paidBetween($payments, $prevFrom, $prevTo));

        $views = \count($this->space->views($provider, $from, $to, PageView::KIND_PROFILE));
        $prevViews = \count($this->space->views($provider, $prevFrom, $prevTo, PageView::KIND_PROFILE));

        $reviews = $this->space->reviews($provider);
        $services = $this->space->services($provider);

        $bookingPoints = static fn (array $list): array => array_map(static fn (Booking $b): array => [$b->getCreatedAt(), 1], $list);
        $paymentPoints = array_map(static fn (Payment $p): array => [$p->getCreatedAt(), (float) $p->getAmount()], $this->space->paidBetween($payments, $from, $to));

        // Top activités : réservations de la période, tendance vs période précédente.
        $top = [];
        foreach ($services as $service) {
            $count = \count(array_filter($current, static fn (Booking $b): bool => $b->getService() === $service));
            $before = \count(array_filter($previous, static fn (Booking $b): bool => $b->getService() === $service));
            if ($count > 0) {
                $top[] = ['service' => $service, 'count' => $count, 'trend' => $this->space->trend($count, $before), 'image' => ProviderSpace::cover($service)];
            }
        }
        usort($top, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        $topMax = $top[0]['count'] ?? 1;

        return $this->renderSpace('provider/space/dashboard.html.twig', 'Tableau de bord', [
            'days' => $days,
            'from' => $from,
            'to' => $to,
            'tiles' => [
                'bookings' => ['value' => \count($current), 'trend' => $this->space->trend(\count($current), \count($previous))],
                'online' => \count(array_filter($services, static fn ($s): bool => ServiceStatus::Published === $s->getStatus())),
                'rating' => $this->space->average($reviews),
                'revenue' => ['value' => $revenue, 'trend' => $this->space->trend($revenue, $prevRevenue)],
                'views' => ['value' => $views, 'trend' => $this->space->trend($views, $prevViews)],
            ],
            'series_current' => $this->space->daily($bookingPoints($current), $from, $days),
            'series_previous' => $this->space->daily($bookingPoints($previous), $prevFrom, $days),
            'revenue_series' => $this->space->daily($paymentPoints, $from, $days),
            'revenue_trend' => $this->space->trend($revenue, $prevRevenue),
            'revenue' => $revenue,
            'recent' => \array_slice($bookings, 0, 4),
            'top' => \array_slice($top, 0, 3),
            'top_max' => $topMax,
            'breakdown' => $this->space->breakdown(array_map(static fn (Booking $b): string => $b->getService()?->getCategory()?->getName() ?? 'Autres', $current)),
            'breakdown_total' => \count($current),
            'notifications' => $this->notificationPresenter->items(\array_slice($this->notifications->findByRecipient($this->currentUser()), 0, 3)),
            'open_requests' => null !== $provider->getMainCategory() ? \count($this->requests->findOpenForCategory($provider->getMainCategory())) : 0,
        ]);
    }

    /**
     * Fiche professionnelle : le formulaire vit désormais dans « Paramètres »
     * (section Profil professionnel). L'URL historique y mène, et reste le
     * point d'enregistrement du formulaire.
     */
    #[Route(path: ['fr' => '/pro/profil', 'en' => '/en/pro/profile'], name: 'app_pro_profile_edit', methods: ['GET', 'POST'])]
    public function editProfile(Request $request): Response
    {
        $provider = $this->currentProvider();

        if (!$request->isMethod('POST')) {
            return $this->redirectToRoute('app_pro_settings', ['section' => 'profil-pro']);
        }

        if (!$this->csrfOk($request)) {
            return $this->redirectToRoute('app_pro_settings', ['section' => 'profil-pro']);
        }

        $displayName = trim((string) $request->request->get('displayName', ''));
        if ('' === $displayName) {
            $this->addFlash('error', 'Le nom affiché ne peut pas être vide.');

            return $this->redirectToRoute('app_pro_settings', ['section' => 'profil-pro']);
        }

        $provider
            ->setDisplayName(mb_substr($displayName, 0, 120))
            ->setBio(self::nullIfEmpty($request->request->get('bio'), 4000))
            ->setCompanyName(self::nullIfEmpty($request->request->get('companyName'), 180))
            ->setCity(self::nullIfEmpty($request->request->get('ville'), 120))
            ->setWebsiteUrl(self::nullIfEmpty($request->request->get('websiteUrl')))
            ->setFacebookUrl(self::nullIfEmpty($request->request->get('facebookUrl')))
            ->setInstagramUrl(self::nullIfEmpty($request->request->get('instagramUrl')))
            ->setLinkedinUrl(self::nullIfEmpty($request->request->get('linkedinUrl')));

        if ($request->request->has('youtubeUrl')) {
            $provider->setYoutubeUrl(self::nullIfEmpty($request->request->get('youtubeUrl')));
        }
        if ($request->request->has('interventionZone')) {
            $provider->setInterventionZone(self::nullIfEmpty($request->request->get('interventionZone'), 180));
        }
        if ($request->request->has('address')) {
            $provider->setAddress(self::nullIfEmpty($request->request->get('address')));
        }

        $categorySlug = (string) $request->request->get('metier', '');
        if ('' !== $categorySlug && null !== $category = $this->categories->findOneBy(['slug' => $categorySlug])) {
            $provider->setMainCategory($category);
        }

        $this->entityManager->flush();
        $this->addFlash('success', 'Votre fiche a été mise à jour.');

        return $this->redirectToRoute('app_pro_settings', ['section' => 'profil-pro']);
    }
}
