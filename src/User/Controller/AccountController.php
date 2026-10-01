<?php

declare(strict_types=1);

namespace App\User\Controller;

use App\Catalog\Entity\Destination;
use App\Catalog\Entity\Service;
use App\Catalog\Presenter\ActivityPresenter;
use App\Catalog\Presenter\DestinationPresenter;
use App\Catalog\Repository\ServiceRepository;
use App\Corporate\Entity\ContactMessage;
use App\Corporate\Service\CorporateInboxService;
use App\Event\Repository\EventRepository;
use App\Favorite\Entity\Favorite;
use App\Favorite\Repository\FavoriteRepository;
use App\Notification\Entity\Notification;
use App\Notification\Entity\NotificationPreference;
use App\Notification\Presenter\NotificationPresenter;
use App\Notification\Repository\NotificationPreferenceRepository;
use App\Notification\Repository\NotificationRepository;
use App\Notification\Service\NotificationService;
use App\Payment\Entity\Payment;
use App\Payment\Enum\PaymentStatus;
use App\Payment\Repository\PaymentRepository;
use App\PrivateActivity\Entity\Participation;
use App\Review\Repository\ReviewRepository;
use App\Shared\Service\AccountIdentityPresenter;
use App\User\Entity\Address;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use App\User\Form\PersonalInformationFormType;
use App\User\Presenter\AccountActivityPresenter;
use App\User\Service\AccountAnonymizer;
use App\User\Service\AccountDataExporter;
use App\User\Service\AvatarStorageService;
use App\User\StaticAccount;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Espace compte « Paramètre du profil » (spec profil) : layout à sidebar
 * persistante + favoris (3 onglets), liste de favoris, notifications,
 * parrainage et confirmation de déconnexion.
 *
 * Depuis le câblage du 17/08, l'IDENTITÉ affichée (nom, e-mail, ancienneté)
 * est celle du compte en session. Le CONTENU (favoris, notifications,
 * parrainage) reste celui des maquettes : il sera branché avec les entités
 * Favorite et Notification. Les états vide/rempli se pilotent par « ?vide=1 ».
 */
#[IsGranted('ROLE_USER')]
final class AccountController extends AbstractController
{
    public function __construct(
        private readonly FavoriteRepository $favorites,
        private readonly ActivityPresenter $activityPresenter,
        private readonly DestinationPresenter $destinationPresenter,
        private readonly AvatarStorageService $avatars,
        private readonly AccountAnonymizer $anonymizer,
        private readonly AccountDataExporter $dataExporter,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventRepository $events,
        private readonly NotificationRepository $notificationRepository,
        private readonly NotificationService $notificationService,
        private readonly NotificationPresenter $notificationPresenter,
        private readonly NotificationPreferenceRepository $notificationPreferences,
        private readonly AccountIdentityPresenter $identityPresenter,
        private readonly AccountActivityPresenter $activity,
        private readonly ReviewRepository $reviews,
        private readonly PaymentRepository $payments,
        private readonly ServiceRepository $services,
    ) {
    }

    /**
     * Tableau de bord client — maquette profil_dashboard_particulier (30/09).
     *
     * Tout est réel : réservations (Booking + participations, voir
     * AccountActivityPresenter), favoris, activités créées, albums,
     * notifications. Les tendances comparent les 30 derniers jours aux 30
     * précédents ; sans période précédente, aucune tendance n'est affichée.
     */
    #[Route(path: ['fr' => '/compte/tableau-de-bord', 'en' => '/en/account/dashboard'], name: 'app_account_dashboard')]
    public function dashboard(Request $request): Response
    {
        $user = $this->currentUser();
        $days = (int) $request->query->get('periode', 30);
        if (!\in_array($days, [7, 30, 90], true)) {
            $days = 30;
        }

        $reservations = $this->activity->reservations($user);
        $created = $this->activity->createdActivities($user);
        $albums = $this->activity->albums($user);
        $favorites = $this->favorites->findBy(['user' => $user]);
        $now = new \DateTimeImmutable();

        $reservationDates = array_map(static fn (array $r): ?\DateTimeImmutable => $r['createdAt'], $reservations);
        $favoriteDates = array_map(static fn (Favorite $f): ?\DateTimeImmutable => $f->getCreatedAt(), $favorites);
        $createdDates = array_map(static fn (array $a): ?\DateTimeImmutable => $a['activity']->getCreatedAt(), $created);
        $spendings = array_filter($reservations, static fn (array $r): bool => null !== $r['amount'] && 'cancelled' !== $r['status']);

        $tiles = [
            ['label' => 'Activités créées', 'value' => (string) \count($created), 'tone' => 'blue', 'icon' => 'edit', 'trend' => $this->activity->trend($createdDates), 'spark' => $this->activity->sparkline($this->activity->dailySeries($createdDates))],
            ['label' => 'Favoris', 'value' => (string) \count($favorites), 'tone' => 'red', 'icon' => 'heart', 'trend' => $this->activity->trend($favoriteDates), 'spark' => $this->activity->sparkline($this->activity->dailySeries($favoriteDates))],
            ['label' => 'Réservations', 'value' => (string) \count($reservations), 'tone' => 'green', 'icon' => 'calendar', 'trend' => $this->activity->trend($reservationDates), 'spark' => $this->activity->sparkline($this->activity->dailySeries($reservationDates))],
            ['label' => 'Dépenses', 'value' => number_format(array_sum(array_column($spendings, 'amount')), 0, ',', ' ').' €', 'tone' => 'orange', 'icon' => 'euro', 'trend' => $this->activity->trend(array_column($spendings, 'createdAt')), 'spark' => $this->activity->sparkline($this->activity->dailySeries(array_column($spendings, 'createdAt')))],
        ];

        $series = $this->activity->dailySeries($reservationDates, $days);
        $upcoming = array_values(array_filter($reservations, static fn (array $r): bool => 'upcoming' === $r['status'] && null !== $r['date'] && $r['date'] >= $now));
        usort($upcoming, static fn (array $a, array $b): int => $a['date'] <=> $b['date']);

        return $this->render('account/tableau_de_bord.html.twig', [
            'user' => $this->accountUser(),
            'menu' => StaticAccount::menu(),
            'active' => 'Tableau de bord',
            'tiles' => $tiles,
            'days' => $days,
            'period_start' => $now->modify(sprintf('-%d days', $days - 1)),
            'period_end' => $now,
            'albums_count' => \count($albums),
            'photos_count' => array_sum(array_column($albums, 'photoCount')),
            'created_count' => \count($created),
            'created_upcoming' => \count(array_filter($created, static fn (array $a): bool => 'online' === $a['status'] || 'full' === $a['status'])),
            'reservations_count' => \count($reservations),
            'reservations_upcoming' => \count($upcoming),
            'series' => $series,
            'breakdown' => $this->activity->categoryBreakdown($reservations),
            'recent_notifications' => $this->notificationPresenter->items(\array_slice($this->notificationRepository->findByRecipient($user), 0, 4)),
            'upcoming' => \array_slice($upcoming, 0, 3),
        ]);
    }

    /**
     * « Paiements et abonnements » — maquette profil_paiements&abonnements_
     * particulier (30/09).
     *
     * Le compte particulier est gratuit : pas d'abonnement (les formules
     * payantes sont celles des professionnels, /pro/abonnement). Aucune carte
     * n'est enregistrée côté plateforme : Stripe la demande à chaque
     * paiement. Les transactions affichées sont les paiements réels des
     * réservations.
     */
    #[Route(path: ['fr' => '/compte/paiements', 'en' => '/en/account/payments'], name: 'app_account_payments')]
    public function payments(): Response
    {
        $user = $this->currentUser();
        $payments = $this->payments->findByClient($user);
        $monthStart = new \DateTimeImmutable('first day of this month 00:00');

        $paid = array_filter($payments, static fn (Payment $p): bool => PaymentStatus::Paid === $p->getStatus());
        $sum = static fn (array $list): float => array_sum(array_map(static fn (Payment $p): float => (float) $p->getAmount(), $list));

        return $this->render('account/paiements.html.twig', [
            'user' => $this->accountUser(),
            'menu' => StaticAccount::menu(),
            'active' => 'Paiements et abonnements',
            'account' => $user,
            'payments' => \array_slice($payments, 0, 5),
            'payments_count' => \count($payments),
            'month_start' => $monthStart,
            'month_total' => $sum(array_filter($paid, static fn (Payment $p): bool => $p->getCreatedAt() >= $monthStart)),
            'total_paid' => $sum($paid),
            'last_paid' => array_values($paid)[0] ?? null,
        ]);
    }

    /** Sujets proposés par le formulaire « Nous contacter » de l'espace compte. */
    private const CONTACT_SUBJECTS = [
        'Question sur une réservation',
        'Paiement et remboursement',
        'Mon compte',
        'Signaler un problème',
        'Suggestion',
        'Autre',
    ];

    /**
     * « Nous contacter » depuis l'espace compte — maquette
     * profil_contact_particulier (30/09). Même boîte de réception que le
     * formulaire public (CorporateInboxService) ; nom et e-mail sont
     * pré-remplis depuis le compte.
     */
    #[Route(path: ['fr' => '/compte/contact', 'en' => '/en/account/contact'], name: 'app_account_contact', methods: ['GET', 'POST'])]
    public function contact(Request $request, CorporateInboxService $inbox): Response
    {
        $user = $this->currentUser();
        $values = [
            'sujet' => '',
            'prenom' => $user->getFirstName(),
            'nom' => $user->getLastName(),
            'email' => $user->getEmail(),
            'telephone' => (string) $user->getPhone(),
            'message' => '',
        ];

        if ($request->isMethod('POST')) {
            foreach (array_keys($values) as $key) {
                $values[$key] = trim((string) $request->request->get($key, ''));
            }

            if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Votre session a expiré, merci de renvoyer le formulaire.');

                return $this->redirectToRoute('app_account_contact');
            }

            $body = $values['message'];
            if ('' !== $values['telephone']) {
                $body .= "\n\nTéléphone : ".$values['telephone'];
            }

            $message = (new ContactMessage())
                ->setName(trim($values['prenom'].' '.$values['nom']))
                ->setEmail($values['email'])
                ->setSubject(\in_array($values['sujet'], self::CONTACT_SUBJECTS, true) ? $values['sujet'] : '')
                ->setMessage($body)
                ->setIpAddress($request->getClientIp());

            $errors = $inbox->submitContact($message);
            if ([] === $errors) {
                $this->addFlash('success', 'Votre message a bien été envoyé. Nous vous répondrons au plus vite.');

                return $this->redirectToRoute('app_account_contact');
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('account/contact.html.twig', [
            'user' => $this->accountUser(),
            'menu' => StaticAccount::menu(),
            'active' => '',
            'subjects' => self::CONTACT_SUBJECTS,
            'values' => $values,
        ]);
    }

    /**
     * Favoris, notifications et déconnexion sont accessibles depuis les deux
     * menus (client et pro, voir StaticAccount::providerMenu()) : un
     * prestataire qui clique dessus depuis son espace pro ne doit pas se
     * retrouver avec la sidebar client. Même règle que
     * ConversationController::menuFor().
     *
     * @return list<array{icon: string, title: string, subtitle: string, route: string|null, badge: string|false}>
     */
    private function menuFor(User $user): array
    {
        return \in_array('ROLE_PROVIDER', $user->getRoles(), true)
            ? StaticAccount::providerMenu()
            : StaticAccount::menu();
    }

    /**
     * L'utilisateur en session, avec la garantie de type qu'attend PHPStan.
     *
     * La classe entière exige ROLE_USER : getUser() ne peut pas être nul ici.
     */
    private function currentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    /**
     * Tout ce qui est affiché sur cet écran est, par définition, en favori :
     * les cœurs sont donc tous actifs, sans réinterroger la base.
     *
     * @return list<array<string, mixed>>
     */
    private function cardsForFavoriteActivities(User $user): array
    {
        $services = $this->favorites->findServicesForUser($user);
        $slugs = array_map(static fn (Service $service): string => $service->getSlug(), $services);

        return $this->activityPresenter->cards($services, favoriteSlugs: $slugs);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cardsForFavoriteDestinations(User $user): array
    {
        $destinations = $this->favorites->findDestinationsForUser($user);
        $slugs = array_map(static fn (Destination $destination): string => $destination->getSlug(), $destinations);

        return $this->destinationPresenter->cards($destinations, $slugs);
    }

    /**
     * Favoris — maquette profil_favoris_particulier (30/09) : activités et
     * lieux (destinations) mis en favori, filtres de la colonne de droite et
     * suggestions tirées du catalogue publié.
     *
     * Les onglets Événements, Hébergements et Restaurants de la maquette
     * n'ont pas d'équivalent : on ne peut mettre en favori que des activités
     * et des destinations (entité Favorite).
     */
    #[Route(path: ['fr' => '/compte/favoris', 'en' => '/en/account/favorites'], name: 'app_account_favorites')]
    public function favorites(Request $request): Response
    {
        $user = $this->currentUser();

        $activities = array_map(static fn (array $c): array => $c + ['kind' => 'activite'], $this->cardsForFavoriteActivities($user));
        $places = array_map(static fn (array $c): array => [
            'slug' => $c['slug'],
            'title' => $c['name'],
            'place' => $c['tagline'] ?? '',
            'rating' => $c['rating'],
            'reviews' => $c['reviews'],
            'image' => $c['image'],
            'kind' => 'destination',
        ], $this->cardsForFavoriteDestinations($user));

        $tab = (string) $request->query->get('onglet', 'toutes');
        $types = (array) $request->query->all('type');
        $query = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('tri', 'recents');
        $region = (string) $request->query->get('region', '');

        $items = match ($tab) {
            'activites' => $activities,
            'lieux' => $places,
            default => array_merge($activities, $places),
        };
        if ([] !== $types) {
            $items = array_filter($items, static fn (array $i): bool => \in_array($i['kind'], $types, true));
        }
        $items = array_values(array_filter($items, static fn (array $i): bool => ('' === $query || false !== mb_stripos($i['title'].' '.$i['place'], $query))
            && ('' === $region || $i['place'] === $region)));
        if ('note' === $sort) {
            usort($items, static fn (array $a, array $b): int => (float) $b['rating'] <=> (float) $a['rating']);
        } elseif ('nom' === $sort) {
            usort($items, static fn (array $a, array $b): int => strcmp($a['title'], $b['title']));
        }

        $favoriteSlugs = $this->favorites->findFavoriteSlugs($user);
        $suggestions = array_values(array_filter(
            $this->activityPresenter->cards($this->services->findPublishedForListing(8)),
            static fn (array $c): bool => !\in_array($c['slug'], $favoriteSlugs['services'], true),
        ));

        $monthStart = new \DateTimeImmutable('first day of this month 00:00');
        $all = array_merge($activities, $places);

        return $this->render('account/favoris.html.twig', [
            'user' => $this->accountUser(),
            'menu' => $this->menuFor($user),
            'active' => 'Favoris',
            'tab' => $tab,
            'items' => $items,
            'query' => $query,
            'sort' => $sort,
            'types' => $types,
            'region' => $region,
            'regions' => array_values(array_unique(array_filter(array_column($all, 'place')))),
            'counts' => [
                'all' => \count($all),
                'activities' => \count($activities),
                'places' => \count($places),
                'month' => \count(array_filter($this->favorites->findBy(['user' => $user]), static fn (Favorite $f): bool => $f->getCreatedAt() >= $monthStart)),
            ],
            'suggestions' => \array_slice($suggestions, 0, 3),
        ]);
    }

    #[Route(path: ['fr' => '/compte/favoris/listes/{slug}', 'en' => '/en/account/favorites/lists/{slug}'], name: 'app_account_favorites_list', defaults: ['slug' => 'alsace-2026'])]
    public function favoritesList(string $slug): Response
    {
        return $this->render('account/liste.html.twig', [
            'user' => $this->accountUser(),
            'menu' => $this->menuFor($this->currentUser()),
            'active' => 'Favoris',
            'list_name' => 'Alsace - 2026',
            'favorites' => StaticAccount::alsaceList(),
        ]);
    }

    /**
     * Notifications — maquette profil_notifications_particulier (30/09) :
     * onglets Toutes / Non lues / Archives (= déjà lues), recherche, filtre
     * par type, 8 par page.
     */
    #[Route(path: ['fr' => '/compte/notifications', 'en' => '/en/account/notifications'], name: 'app_account_notifications')]
    public function notifications(Request $request): Response
    {
        $user = $this->currentUser();
        $all = $this->notificationRepository->findByRecipient($user);
        $unread = \count(array_filter($all, static fn (Notification $n): bool => !$n->isRead()));

        $filter = (string) $request->query->get('filtre', 'toutes');
        if (!\in_array($filter, ['toutes', 'non-lues', 'archives'], true)) {
            $filter = 'toutes';
        }
        $query = trim((string) $request->query->get('q', ''));
        $type = (string) $request->query->get('type', '');

        $filtered = array_values(array_filter($all, static fn (Notification $n): bool => match ($filter) {
            'non-lues' => !$n->isRead(),
            'archives' => $n->isRead(),
            default => true,
        }
            && ('' === $type || $n->getCategory()->value === $type)
            && ('' === $query || false !== mb_stripos($n->getTitle().' '.$n->getMessage(), $query))));

        $perPage = 8;
        $total = \count($filtered);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, $request->query->getInt('page', 1)));

        $preference = $this->notificationPreferences->findOneByUser($user);

        return $this->render('account/notifications.html.twig', [
            'user' => $this->accountUser(),
            'menu' => $this->menuFor($user),
            'active' => 'Notifications',
            'filter' => $filter,
            'query' => $query,
            'type' => $type,
            'unread' => $unread,
            'items' => $this->notificationPresenter->items(\array_slice($filtered, ($page - 1) * $perPage, $perPage)),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'per_page' => $perPage,
            'email_enabled' => $preference?->isEmailEnabled() ?? true,
        ]);
    }

    #[Route(path: ['fr' => '/compte/notifications/tout-marquer-lu', 'en' => '/en/account/notifications/mark-all-read'], name: 'app_account_notifications_mark_all_read', methods: ['POST'])]
    public function markAllNotificationsRead(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return $this->redirectToRoute('app_account_notifications');
        }

        $this->notificationService->markAllAsRead($this->currentUser());

        return $this->redirectToRoute('app_account_notifications');
    }

    /**
     * Préférences de notification (Lot K, 16/09 ; §7.2 et §8.3 du CDC).
     * Seul le canal e-mail est proposé : c'est le seul réellement câblé
     * (NotificationEmailListener) — le canal push n'a aucune infrastructure
     * d'envoi, l'exposer serait un réglage sans effet.
     */
    #[Route(path: ['fr' => '/compte/notifications/preferences', 'en' => '/en/account/notifications/preferences'], name: 'app_account_notification_preferences', methods: ['GET', 'POST'])]
    public function notificationPreferences(Request $request): Response
    {
        $user = $this->currentUser();
        $preference = $this->notificationPreferences->findOneByUser($user);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

                return $this->redirectToRoute('app_account_notification_preferences');
            }

            if (null === $preference) {
                $preference = (new NotificationPreference())->setUser($user);
                $this->entityManager->persist($preference);
            }

            $preference->setEmailEnabled($request->request->getBoolean('email_enabled'));
            $this->entityManager->flush();

            $this->addFlash('success', 'Vos préférences ont été enregistrées.');

            return $this->redirectToRoute('app_account_notification_preferences');
        }

        return $this->render('account/notification_preferences.html.twig', [
            'user' => $this->accountUser(),
            'menu' => $this->menuFor($user),
            'active' => 'Notifications',
            'email_enabled' => $preference?->isEmailEnabled() ?? true,
        ]);
    }

    #[Route(path: ['fr' => '/compte/parrainage', 'en' => '/en/account/referral'], name: 'app_account_referral')]
    public function referral(): Response
    {
        return $this->render('account/parrainage.html.twig', [
            'user' => $this->accountUser(),
            'menu' => StaticAccount::menu(),
            'active' => 'Parrainage',
        ]);
    }

    /**
     * « Mes activités créées » de la sidebar (Lot J, 15/09) : les événements
     * (domaine Event) que l'utilisateur a lui-même organisés.
     */
    #[Route(path: ['fr' => '/compte/mes-evenements', 'en' => '/en/account/my-events'], name: 'app_account_events')]
    public function events(): Response
    {
        return $this->render('account/mes_evenements.html.twig', [
            'user' => $this->accountUser(),
            'menu' => StaticAccount::menu(),
            'active' => 'Mes activités créées',
            'events' => $this->events->findByOrganizer($this->currentUser()),
        ]);
    }

    /**
     * « Mes réservations » — maquette profil_reservations_particulier (30/09) :
     * réservations payantes (Booking) et places dans des activités privées
     * (Participation) dans un même tableau, voir AccountActivityPresenter.
     */
    #[Route(path: ['fr' => '/compte/historique', 'en' => '/en/account/history'], name: 'app_account_history')]
    public function history(Request $request): Response
    {
        $user = $this->currentUser();
        $all = $this->activity->reservations($user);

        $tabs = ['toutes' => null, 'a-venir' => 'upcoming', 'passees' => 'past', 'annulees' => 'cancelled'];
        $tab = (string) $request->query->get('onglet', 'toutes');
        if (!\array_key_exists($tab, $tabs)) {
            $tab = 'toutes';
        }
        $query = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('tri', 'recentes');
        $period = (string) $request->query->get('periode', '');
        $status = (string) $request->query->get('statut', '');
        $since = match ($period) {
            '30j' => new \DateTimeImmutable('-30 days'),
            'annee' => new \DateTimeImmutable('first day of january this year 00:00'),
            default => null,
        };

        $rows = array_values(array_filter($all, static fn (array $r): bool => (null === $tabs[$tab] || $r['status'] === $tabs[$tab])
            && ('' === $status || $r['status'] === $status)
            && (null === $since || ($r['date'] ?? $r['createdAt']) >= $since)
            && ('' === $query || false !== mb_stripos($r['title'].' '.$r['place'], $query))));

        usort($rows, match ($sort) {
            'anciennes' => static fn (array $a, array $b): int => ($a['date'] ?? $a['createdAt']) <=> ($b['date'] ?? $b['createdAt']),
            'montant' => static fn (array $a, array $b): int => ($b['amount'] ?? 0) <=> ($a['amount'] ?? 0),
            default => static fn (array $a, array $b): int => ($b['date'] ?? $b['createdAt']) <=> ($a['date'] ?? $a['createdAt']),
        });

        $perPage = 6;
        $total = \count($rows);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, $request->query->getInt('page', 1)));

        $count = static fn (string $s): int => \count(array_filter($all, static fn (array $r): bool => $r['status'] === $s));
        $recent = array_filter($all, static fn (array $r): bool => $r['createdAt'] >= new \DateTimeImmutable('-30 days'));
        $spent = array_filter($all, static fn (array $r): bool => null !== $r['amount'] && 'cancelled' !== $r['status']);
        $now = new \DateTimeImmutable();
        $next = array_values(array_filter($all, static fn (array $r): bool => 'upcoming' === $r['status'] && null !== $r['date'] && $r['date'] >= $now));
        usort($next, static fn (array $a, array $b): int => $a['date'] <=> $b['date']);
        $monthStart = new \DateTimeImmutable('first day of this month 00:00');

        return $this->render('account/historique.html.twig', [
            'user' => $this->accountUser(),
            'menu' => StaticAccount::menu(),
            'active' => 'Mes réservations',
            'rows' => \array_slice($rows, ($page - 1) * $perPage, $perPage),
            'tab' => $tab,
            'query' => $query,
            'sort' => $sort,
            'period' => $period,
            'status' => $status,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'per_page' => $perPage,
            'counts' => [
                'all' => \count($all),
                'upcoming' => $count('upcoming'),
                'past' => $count('past'),
                'cancelled' => $count('cancelled'),
                'month' => \count(array_filter($all, static fn (array $r): bool => $r['createdAt'] >= $monthStart)),
            ],
            'summary' => [
                'spent' => array_sum(array_column(array_filter($recent, static fn (array $r): bool => null !== $r['amount'] && 'cancelled' !== $r['status']), 'amount')),
                'spentTrend' => $this->activity->trend(array_column($spent, 'createdAt')),
                'count' => \count($recent),
                'countTrend' => $this->activity->trend(array_column($all, 'createdAt')),
                'distinct' => \count(array_unique(array_column($recent, 'title'))),
            ],
            'next' => $next[0] ?? null,
        ]);
    }

    /**
     * « Exporter mes réservations » : fichier CSV (séparateur « ; », lisible
     * directement par Excel en français).
     */
    #[Route(path: ['fr' => '/compte/historique/exporter', 'en' => '/en/account/history/export'], name: 'app_account_history_export')]
    public function exportHistory(): Response
    {
        $lines = [['Activité', 'Lieu', 'Date', 'Participants', 'Montant (EUR)', 'Statut', 'Détail']];
        foreach ($this->activity->reservations($this->currentUser()) as $r) {
            $lines[] = [
                $r['title'],
                $r['place'],
                $r['date']?->format('d/m/Y H:i') ?? '',
                (string) $r['participants'],
                null === $r['amount'] ? '' : number_format($r['amount'], 2, ',', ''),
                $r['statusLabel'],
                $r['statusNote'],
            ];
        }

        $handle = fopen('php://temp', 'r+');
        \assert(false !== $handle);
        fwrite($handle, "\xEF\xBB\xBF");
        foreach ($lines as $line) {
            fputcsv($handle, $line, ';', '"', '');
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="mes-reservations-trouvemoi.csv"',
        ]);
    }

    /**
     * « Informations personnelles » — maquette profil_infos_particulier
     * (30/09) : identité, photo, coordonnées et adresse principale.
     */
    #[Route(path: ['fr' => '/compte/informations', 'en' => '/en/account/personal-information'], name: 'app_account_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request): Response
    {
        $user = $this->currentUser();
        $address = $user->getMainAddress();

        $form = $this->createForm(PersonalInformationFormType::class, $user);
        if (!$form->isSubmitted() && null !== $address) {
            $form->get('addressLine1')->setData($address->getLine1());
            $form->get('addressLine2')->setData($address->getLine2());
            $form->get('postalCode')->setData($address->getPostalCode());
            $form->get('city')->setData($address->getCity());
            $form->get('addressCountry')->setData($address->getCountry());
        }
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $line1 = trim((string) $form->get('addressLine1')->getData());
            $postalCode = trim((string) $form->get('postalCode')->getData());
            $city = trim((string) $form->get('city')->getData());
            $country = (string) ($form->get('addressCountry')->getData() ?? $user->getCountry() ?? 'FR');
            $line2 = trim((string) $form->get('addressLine2')->getData());
            $anyAddress = '' !== $line1 || '' !== $postalCode || '' !== $city || '' !== $line2;

            if ($anyAddress && ('' === $line1 || '' === $postalCode || '' === $city)) {
                $form->get('addressLine1')->addError(new FormError('Adresse, code postal et ville sont nécessaires pour enregistrer une adresse.'));
            }

            if ($form->isValid()) {
                $photo = $form->get('photo')->getData();
                if ($photo instanceof UploadedFile) {
                    try {
                        $this->avatars->store($user, $photo);
                    } catch (\InvalidArgumentException $e) {
                        $this->addFlash('error', $e->getMessage());

                        return $this->redirectToRoute('app_account_profile');
                    }
                }

                if ($anyAddress) {
                    if (null === $address) {
                        $address = (new Address())->setLabel('Domicile');
                        $user->addAddress($address);
                    }
                    $address->setLine1($line1)->setLine2('' !== $line2 ? $line2 : null)->setPostalCode($postalCode)->setCity($city)->setCountry($country);
                }

                $this->entityManager->flush();
                $this->addFlash('success', 'Vos informations ont été mises à jour.');

                return $this->redirectToRoute('app_account_profile');
            }
        }

        return $this->render('account/informations.html.twig', [
            'user' => $this->accountUser(),
            'menu' => StaticAccount::menu(),
            'active' => 'Informations personnelles',
            'account' => $user,
            'form' => $form,
        ], new Response(null, $form->isSubmitted() ? 422 : 200));
    }

    /**
     * Paramètres du compte — maquette profil_parametres_particulier (30/09) :
     * résumé du compte, sécurité, langue, export des données, désactivation et
     * suppression. La modification des informations se fait dans
     * « Informations personnelles ».
     */
    #[Route(path: ['fr' => '/compte/parametres', 'en' => '/en/account/settings'], name: 'app_account_settings', methods: ['GET'])]
    public function settings(): Response
    {
        $user = $this->currentUser();

        return $this->render('account/parametres.html.twig', [
            'user' => $this->accountUser(),
            'menu' => StaticAccount::menu(),
            'active' => 'Paramètres du compte',
            'account' => $user,
            'published_count' => \count($this->activity->createdActivities($user)),
            'reservations_count' => \count($this->activity->reservations($user)),
            'reviews_count' => \count($this->reviews->findByAuthor($user)),
        ]);
    }

    /**
     * Export des données personnelles (Lot K, 16/09 ; §26 du CDC — droit à
     * la portabilité). GET, pas POST : contrairement à la désactivation ou
     * la suppression ci-dessous, télécharger un export ne modifie aucun état,
     * un jeton CSRF n'aurait rien à protéger.
     */
    #[Route(path: ['fr' => '/compte/parametres/exporter', 'en' => '/en/account/settings/export'], name: 'app_account_export')]
    public function exportData(): Response
    {
        $data = $this->dataExporter->export($this->currentUser());

        $response = new JsonResponse($data, headers: [
            'Content-Disposition' => 'attachment; filename="mes-donnees-trouvemoi.json"',
        ]);
        $response->setEncodingOptions(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return $response;
    }

    /**
     * Désactivation : statut suspendu, réversible par l'administration —
     * distincte de la suppression ci-dessous, qui efface les données.
     */
    #[Route(path: ['fr' => '/compte/parametres/desactiver', 'en' => '/en/account/settings/deactivate'], name: 'app_account_deactivate', methods: ['POST'])]
    public function deactivate(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return $this->redirectToRoute('app_account_settings');
        }

        $user = $this->currentUser();
        $user->setStatus(UserStatus::Suspended);
        $this->entityManager->flush();

        $this->addFlash('success', 'Votre compte a été désactivé. Contactez-nous pour le réactiver.');

        return $this->redirectToRoute('app_account_logout_confirm');
    }

    /**
     * Suppression : irréversible, efface les données personnelles
     * (AccountAnonymizer, voir ce service pour le détail RGPD).
     */
    #[Route(path: ['fr' => '/compte/parametres/supprimer', 'en' => '/en/account/settings/delete'], name: 'app_account_delete', methods: ['POST'])]
    public function delete(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return $this->redirectToRoute('app_account_settings');
        }

        $this->anonymizer->anonymize($this->currentUser());

        $this->addFlash('success', 'Votre compte et vos données personnelles ont été supprimés.');

        return $this->redirectToRoute('app_account_logout_confirm');
    }

    #[Route(path: ['fr' => '/compte/deconnexion', 'en' => '/en/account/sign-out'], name: 'app_account_logout_confirm')]
    public function logoutConfirm(): Response
    {
        return $this->render('account/deconnexion.html.twig', [
            'user' => $this->accountUser(),
            'menu' => $this->menuFor($this->currentUser()),
            'active' => 'Déconnexion',
        ]);
    }

    /**
     * Le bloc « profil » de la sidebar, alimenté par le compte en session.
     *
     * Déléguée à AccountIdentityPresenter (18/09) : cette méthode avait sa
     * propre copie du même calcul, volontairement laissée de côté lors de
     * l'extraction du 14/09 (« /compte/* continue de fonctionner sans
     * dépendre de ce partage », voir l'historique de AccountIdentityPresenter)
     * — jusqu'à ce que l'ajout des badges « Demandes reçues »/« Avis reçus »
     * y révèle le vrai coût de la copie : cette version-ci ne les connaissait
     * pas, et `account/_sidebar.html.twig` plantait (clé manquante) pour tout
     * compte pro passant par un des dix écrans de ce contrôleur.
     *
     * @return array{name: string, firstName: string, email: string, avatar: string, memberSince: string, unreadMessages: int, unreadNotifications: int, pendingProviderRequests: int, pendingProviderReviews: int}
     */
    private function accountUser(): array
    {
        return $this->identityPresenter->identityFor($this->currentUser());
    }
}
