<?php

declare(strict_types=1);

namespace App\Event\Controller;

use App\Event\Entity\Event;
use App\Event\Entity\EventRegistration;
use App\Event\Entity\Group;
use App\Event\Entity\GroupAlbum;
use App\Event\Presenter\CalendarPresenter;
use App\Event\Presenter\EventPresenter;
use App\Event\Presenter\GroupPresenter;
use App\Event\Repository\EventCategoryRepository;
use App\Event\Repository\EventInvitationRepository;
use App\Event\Repository\EventRegistrationRepository;
use App\Event\Repository\EventRepository;
use App\Event\Repository\GroupAlbumRepository;
use App\Event\Repository\GroupRepository;
use App\Event\StaticEvents;
use App\I18n\Routing\LocaleUrlGenerator;
use App\Notification\Enum\NotificationCategory;
use App\Notification\Service\NotificationService;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

/**
 * Flow navigation Événements (spec « Partie 2 — Événements ») :
 * landing, listings événements/groupes, détail événement + participants,
 * détail groupe (5 onglets), album, demande d'adhésion, calendrier global
 * et événements privés. Pas de conflit avec /evenements/creer/{etape}
 * (requirement [1-8] du wizard).
 */
final class EventsController extends AbstractController
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly EventPresenter $presenter,
        private readonly GroupRepository $groups,
        private readonly GroupAlbumRepository $albums,
        private readonly GroupPresenter $groupPresenter,
        private readonly CalendarPresenter $calendarPresenter,
        private readonly EventInvitationRepository $invitations,
    ) {
    }

    private function findEventOrFail(string $slug): Event
    {
        $event = $this->events->findOneBySlug($slug);

        if (null === $event) {
            throw new NotFoundHttpException('Cet événement est introuvable.');
        }

        return $event;
    }

    private function findGroupOrFail(string $slug): Group
    {
        $group = $this->groups->findOneBySlug($slug);

        if (null === $group) {
            throw new NotFoundHttpException('Ce groupe est introuvable.');
        }

        return $group;
    }

    /**
     * La rangee « filtree » de l'ecran « Tous les evenements ».
     *
     * La maquette y remet quatre cartes deja presentes plus haut, dans un
     * autre ordre : randonnee, foot, barbecue, yoga. C'est un effet de
     * presentation, pas un filtre — d'ou sa place ici.
     *
     * @param list<array<string, mixed>> $events
     *
     * @return list<array<string, mixed>>
     */
    private function filteredRow(array $events): array
    {
        // Neuf cartes, dans l'ordre exact de la maquette : elle reprend celles
        // du dessus dans un autre ordre et repete le barbecue en dernier.
        $rangs = [4, 1, 2, 3, 8, 9, 10, 11, 2];
        $rangee = [];

        foreach ($rangs as $rang) {
            if (isset($events[$rang])) {
                $rangee[] = $events[$rang];
            }
        }

        return $rangee;
    }

    /** Pastilles de catégories de la maquette evenements.jpeg (libellé → slug EventCategory). */
    private const CHIPS = [
        '' => ['Tous les événements', 'star'],
        'sports' => ['Sport', 'zap'],
        'randonnee' => ['Randonnée', 'cat_hiking'],
        'repas' => ['Repas & Fête', 'cheers'],
        'culture' => ['Culture & Loisirs', 'palette'],
        'bien-etre' => ['Bien-être', 'leaf'],
        'jeu' => ['Jeux & Loisirs', 'puzzle'],
        'en-famille' => ['En famille', 'users'],
    ];

    private const PER_PAGE = 10;

    /**
     * « Événements » — maquette docs/maquettes/evenements.jpeg (04/10) :
     * recherche (où, quand, catégorie, type), pastilles, tri, grille,
     * « Charger plus », carte des événements et « à venir ».
     */
    #[Route(path: ['fr' => '/evenements', 'en' => '/en/events'], name: 'app_events')]
    public function index(Request $request, EventCategoryRepository $categoryRepository): Response
    {
        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'where' => trim((string) $request->query->get('ou', '')),
            'when' => (string) $request->query->get('quand', ''),
            'date' => (string) $request->query->get('date', ''),
            'category' => (string) $request->query->get('categorie', ''),
            'type' => (string) $request->query->get('type', ''),
            'sort' => (string) $request->query->get('tri', 'date'),
        ];
        $page = max(1, $request->query->getInt('page', 1));
        [$events, $total] = $this->events->searchPublic($filters, self::PER_PAGE * $page);
        [$upcoming] = $this->events->searchPublic(['sort' => 'date'], 4);
        [$all] = $this->events->searchPublic([], 300);

        $markers = [];
        foreach ($this->presenter->cards($all) as $card) {
            if (null !== $card['lat'] && null !== $card['lng']) {
                $markers[] = ['lat' => $card['lat'], 'lng' => $card['lng'], 'title' => $card['title'], 'url' => $this->generateUrl('app_events_detail', ['slug' => $card['slug']]), 'date' => $card['day'].' '.$card['month'], 'where' => $card['where']];
            }
        }

        return $this->render('event/nav/index.html.twig', [
            'events' => $this->presenter->cards($events),
            'total' => $total,
            'page' => $page,
            'has_more' => $total > \count($events),
            'filters' => $filters,
            // Mêmes filtres sous leurs noms d'URL, pour les liens et formulaires.
            'params' => array_filter(['q' => $filters['q'], 'ou' => $filters['where'], 'quand' => $filters['when'], 'date' => $filters['date'], 'categorie' => $filters['category'], 'type' => $filters['type'], 'tri' => 'date' !== $filters['sort'] ? $filters['sort'] : '']),
            'chips' => self::CHIPS,
            'categories' => $categoryRepository->findBy([], ['position' => 'ASC']),
            'types' => \App\Event\StaticEventWizard::types(),
            'upcoming' => $this->presenter->cards($upcoming),
            'markers' => $markers,
            'avatars' => StaticEvents::avatars(),
        ]);
    }

    #[Route(path: ['fr' => '/evenements/tous', 'en' => '/en/events/all'], name: 'app_events_all')]
    public function all(): Response
    {
        $events = $this->presenter->cards($this->events->findForListing());

        return $this->render('event/nav/tous.html.twig', [
            'events' => $events,
            // La rangee « filtree » de la maquette est une SELECTION d'ordre
            // (randonnee, foot, barbecue...) et non un filtre reel : c'est une
            // mise en page, elle reste ici et non en base.
            'events_filtered' => $this->filteredRow($events),
            'avatars' => StaticEvents::avatars(),
            'selections' => StaticEvents::selections(),
            'cities' => StaticEvents::cities(),
        ]);
    }

    #[Route(path: ['fr' => '/evenements/calendrier', 'en' => '/en/events/calendar'], name: 'app_events_calendar')]
    public function calendar(Request $request): Response
    {
        return $this->render('event/nav/calendrier.html.twig', [
            ...$this->calendarData($this->readMonth($request)),
            'selections' => StaticEvents::selections(),
            'cities' => StaticEvents::cities(),
        ]);
    }

    /**
     * Les variables du calendrier mensuel, communes a l'ecran Calendrier et a
     * l'onglet « Evenements » d'un groupe, qui affichent le meme composant.
     *
     * @return array<string, mixed>
     */
    private function calendarData(?\DateTimeImmutable $mois = null): array
    {
        $mois ??= $this->events->findDefaultCalendarMonth();
        $debut = $mois->modify('first day of this month')->setTime(0, 0);
        $fin = $debut->modify('+1 month');

        return [
            'calendar' => $this->calendarPresenter->grid($debut, $this->events->findBetween($debut, $fin)),
            'monthLabel' => $this->calendarPresenter->monthLabel($debut),
            'prevMonth' => $debut->modify('-1 month')->format('Y-m'),
            'nextMonth' => $fin->format('Y-m'),
        ];
    }

    /**
     * Mois demande par l'URL (« ?mois=2026-05 »).
     *
     * Sans parametre, on ouvre sur le mois du prochain evenement : ouvrir sur
     * un mois vide alors que le site en propose donnerait l'impression qu'il
     * n'y en a aucun. Un parametre illisible retombe sur ce meme defaut plutot
     * que de provoquer une erreur.
     */
    private function readMonth(Request $request): \DateTimeImmutable
    {
        $demande = (string) $request->query->get('mois', '');

        if (1 === preg_match('/^\d{4}-\d{2}$/', $demande)) {
            $mois = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $demande.'-01 00:00:00');

            if (false !== $mois) {
                return $mois;
            }
        }

        return $this->events->findDefaultCalendarMonth();
    }

    #[Route(path: ['fr' => '/evenements/prives', 'en' => '/en/events/private'], name: 'app_events_private')]
    public function private(): Response
    {
        return $this->render('event/nav/prives.html.twig', [
            // Aucun evenement n'est marque prive : la maquette montre le meme
            // listing sur cet onglet. On le conserve tel quel plutot que
            // d'afficher une page vide, le temps que la creation d'evenements
            // prives existe.
            // Événements privés : seulement ceux que le membre organise ou
            // auxquels il est invité (04/10) ; rien pour un visiteur.
            'events' => $this->presenter->cards($this->getUser() instanceof User ? $this->events->findPrivateFor($this->getUser()) : []),
            'avatars' => StaticEvents::avatars(),
            'selections' => StaticEvents::selections(),
            'cities' => StaticEvents::cities(),
        ]);
    }

    #[Route(path: ['fr' => '/evenements/detail/{slug}', 'en' => '/en/events/detail/{slug}'], name: 'app_events_detail')]
    public function detail(string $slug, EventRegistrationRepository $registrations): Response
    {
        $event = $this->viewableOrFail($slug);
        $user = $this->getUser();
        $going = $registrations->findGoing($event);

        return $this->render('event/nav/detail.html.twig', [
            'event' => $this->presenter->card($event),
            'entity' => $event,
            'registration' => $user instanceof User ? $registrations->findOneFor($event, $user) : null,
            'is_organizer' => $user instanceof User && $event->getOrganizer() === $user,
            'going' => $going,
            'waitlist_count' => $registrations->count(['event' => $event, 'status' => EventRegistration::WAITLIST]),
            'similar' => $this->presenter->cards(array_slice(array_filter(
                $this->events->findForListing(limit: 5),
                static fn (Event $e): bool => $e !== $event,
            ), 0, 4)),
            'avatars' => StaticEvents::avatars(),
        ]);
    }

    /**
     * « Je participe » / « Me désinscrire » (04/10) : inscription réelle,
     * liste d'attente quand l'événement est complet, promotion du premier en
     * attente quand une place se libère.
     */
    #[Route(path: ['fr' => '/evenements/detail/{slug}/participer', 'en' => '/en/events/detail/{slug}/join'], name: 'app_events_join', methods: ['POST'])]
    public function join(string $slug, Request $request, EventRegistrationRepository $registrations, EntityManagerInterface $em, NotificationService $notifications): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('info', 'Connectez-vous pour participer à cet événement.');

            return $this->redirectToRoute('app_login');
        }
        $event = $this->viewableOrFail($slug);
        if (!$this->isCsrfTokenValid('event_join', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return $this->redirectToRoute('app_events_detail', ['slug' => $slug]);
        }

        $existing = $registrations->findOneFor($event, $user);
        if (null !== $existing) {
            if ($event->getOrganizer() === $user) {
                $this->addFlash('info', 'Vous êtes l’organisateur de cet événement.');

                return $this->redirectToRoute('app_events_detail', ['slug' => $slug]);
            }
            $wasGoing = EventRegistration::GOING === $existing->getStatus();
            $em->remove($existing);
            $em->flush();
            if ($wasGoing && null !== ($next = $registrations->findOneBy(['event' => $event, 'status' => EventRegistration::WAITLIST], ['createdAt' => 'ASC']))) {
                $next->setStatus(EventRegistration::GOING);
                $notifications->notify($next->getUser(), NotificationCategory::Activity, 'Une place s’est libérée', sprintf('Vous participez désormais à « %s ».', $event->getTitle()));
            }
            $this->addFlash('success', 'Votre participation a été annulée.');
        } elseif ($event->getStartsAt() < new \DateTimeImmutable()) {
            $this->addFlash('error', 'Cet événement a déjà commencé.');

            return $this->redirectToRoute('app_events_detail', ['slug' => $slug]);
        } else {
            $full = null !== $event->getCapacity() && $registrations->countGoing($event) >= $event->getCapacity();
            if ($full && !$event->isWaitlist()) {
                $this->addFlash('error', 'Cet événement est complet.');

                return $this->redirectToRoute('app_events_detail', ['slug' => $slug]);
            }
            $em->persist(new EventRegistration($event, $user, $full ? EventRegistration::WAITLIST : EventRegistration::GOING));
            $this->addFlash('success', $full ? 'Événement complet : vous êtes sur la liste d’attente, nous vous préviendrons si une place se libère.' : sprintf('C’est noté, vous participez à « %s » !', $event->getTitle()));
            if (null !== ($organizer = $event->getOrganizer()) && $organizer !== $user) {
                $notifications->notify($organizer, NotificationCategory::Activity, $full ? 'Nouvelle personne en liste d’attente' : 'Nouveau participant', sprintf('%s %s · « %s »', $user->getFirstName(), $user->getLastName(), $event->getTitle()));
            }
        }

        $em->flush();
        $event->setParticipantsCount($registrations->countGoing($event));
        $em->flush();

        return $this->redirectToRoute('app_events_detail', ['slug' => $slug]);
    }

    /** « Ajouter à votre agenda » : fichier iCalendar de l'événement. */
    #[Route(path: ['fr' => '/evenements/detail/{slug}/agenda.ics', 'en' => '/en/events/detail/{slug}/calendar.ics'], name: 'app_events_ics')]
    public function ics(string $slug): Response
    {
        $event = $this->viewableOrFail($slug);
        $utc = new \DateTimeZone('UTC');
        $fmt = static fn (\DateTimeImmutable $d): string => $d->setTimezone($utc)->format('Ymd\THis\Z');
        $esc = static fn (?string $t): string => str_replace(['\\', ';', ',', "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', ''], (string) $t);
        $lines = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//TrouveMoi//Evenements//FR', 'BEGIN:VEVENT',
            'UID:'.$event->getSlug().'@trouvemoi.eu',
            'DTSTAMP:'.$fmt(new \DateTimeImmutable()),
            'DTSTART:'.$fmt($event->getStartsAt()),
            'DTEND:'.$fmt($event->getEndsAt() ?? $event->getStartsAt()->modify('+2 hours')),
            'SUMMARY:'.$esc($event->getTitle()),
            'LOCATION:'.$esc($event->getAddress() ?? $event->getLocation()),
            'DESCRIPTION:'.$esc($event->getShortDescription()),
            'URL:'.$this->generateUrl('app_events_detail', ['slug' => $event->getSlug()], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL),
            'END:VEVENT', 'END:VCALENDAR',
        ];

        return new Response(implode("\r\n", $lines)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => sprintf('attachment; filename="%s.ics"', $event->getSlug()),
        ]);
    }

    #[Route(path: ['fr' => '/evenements/detail/{slug}/participants', 'en' => '/en/events/detail/{slug}/participants'], name: 'app_events_participants')]
    public function participants(string $slug, EventRegistrationRepository $registrations): Response
    {
        $event = $this->viewableOrFail($slug);
        $user = $this->getUser();
        $canSee = $event->isShowParticipants() || ($user instanceof User && $event->getOrganizer() === $user);

        return $this->render('event/nav/participants.html.twig', [
            'event' => $this->presenter->card($event),
            'entity' => $event,
            'going' => $canSee ? $registrations->findGoing($event) : [],
            'hidden_list' => !$canSee,
        ]);
    }

    /**
     * Fiche consultable : publiée (ou programmée et arrivée à échéance) ;
     * un brouillon ou un événement privé ne l'est que par son organisateur
     * et ses invités.
     */
    private function viewableOrFail(string $slug): Event
    {
        $event = $this->findEventOrFail($slug);
        $user = $this->getUser();
        $isOrganizer = $user instanceof User && $event->getOrganizer() === $user;
        if (!$isOrganizer && !$event->isPublished()) {
            throw new NotFoundHttpException('Cet événement est introuvable.');
        }
        if (!$isOrganizer && $event->isPrivate()) {
            $invited = $user instanceof User && $this->invitations->isInvited($event, $user);
            if (!$invited) {
                throw $user instanceof User ? $this->createAccessDeniedException('Cet événement est réservé aux personnes invitées.') : new NotFoundHttpException('Cet événement est introuvable.');
            }
        }

        return $event;
    }

    #[Route(path: ['fr' => '/evenements/groupes', 'en' => '/en/events/groups'], name: 'app_groups')]
    public function groups(): Response
    {
        return $this->render('event/nav/groupes.html.twig', [
            'groups' => $this->groupPresenter->cards($this->groups->findForListing()),
            'avatars' => StaticEvents::avatars(),
        ]);
    }

    #[Route(path: ['fr' => '/evenements/groupes/detail/{groupSlug}/photos/album/{albumId}', 'en' => '/en/events/groups/detail/{groupSlug}/photos/album/{albumId}'], name: 'app_group_album')]
    public function album(string $groupSlug, string $albumId): Response
    {
        $group = $this->findGroupOrFail($groupSlug);
        $album = Ulid::isValid($albumId) ? $this->albums->find(Ulid::fromString($albumId)) : null;

        if (!$album instanceof GroupAlbum || $album->getGroup() !== $group) {
            throw new NotFoundHttpException('Cet album est introuvable.');
        }

        return $this->render('event/nav/album.html.twig', [
            'group' => $this->groupPresenter->card($group),
            'album' => $this->groupPresenter->albums([$album])[0],
            // « Événements similaires à proximité » : mêmes cartes réelles
            // que sur la fiche groupe (groupDetail()) et la fiche événement.
            'similar' => $this->presenter->cards($this->events->findForListing(limit: 4)),
            'avatars' => StaticEvents::avatars(),
        ]);
    }

    #[Route(path: ['fr' => '/evenements/groupes/detail/demande-envoyee', 'en' => '/en/events/groups/detail/request-sent'], name: 'app_group_join_sent')]
    public function joinSent(): Response
    {
        return $this->render('event/nav/demande.html.twig');
    }

    // L'onglet apparait dans l'URL : il accepte donc les deux langues
    // (/detail/membres et /en/detail/members). tabKey() ramene ensuite la
    // valeur a l'identifiant interne attendu par les gabarits.
    #[Route(path: ['fr' => '/evenements/groupes/detail/{slug}/{onglet}', 'en' => '/en/events/groups/detail/{slug}/{onglet}'], name: 'app_group_detail', requirements: ['onglet' => 'apropos|evenements|membres|photos|discussions|about|events|members'], defaults: ['onglet' => 'apropos'])]
    public function groupDetail(string $slug, string $onglet): Response
    {
        $group = $this->findGroupOrFail($slug);
        $onglet = LocaleUrlGenerator::tabKey($onglet);

        $now = new \DateTimeImmutable();
        $events = $this->events->findForListing();
        $upcoming = array_values(array_filter($events, static fn (Event $e): bool => $e->getStartsAt() >= $now));
        $past = array_values(array_filter($events, static fn (Event $e): bool => $e->getStartsAt() < $now));

        return $this->render('event/nav/groupe.html.twig', [
            'group' => $this->groupPresenter->card($group),
            'tab' => $onglet,
            // Evenements reels a la place des groupes de remplissage de la
            // maquette (StaticEvents::groupEvents(), point a trancher n°5,
            // desormais tranche : on n'invente plus un habillage de groupe
            // pour des evenements). Montre pour l'instant TOUS les evenements,
            // faute de lien Event <-> Group en base — ce rattachement reste a
            // concevoir le jour ou le produit en a besoin.
            'group_events' => $this->presenter->cards($events),
            'upcoming_events' => $this->presenter->cards(\array_slice($upcoming, 0, 3)),
            'past_events' => $this->presenter->cards(\array_slice($past, 0, 3)),
            // Les membres sont des prenoms et des photos de la maquette, sans
            // compte derriere : il n'y a rien a brancher tant que l'adhesion a
            // un groupe n'existe pas.
            'members' => StaticEvents::members(),
            'albums' => $this->groupPresenter->albums($this->albums->findForGroup($group)),
            // « Evenements similaires a proximite », commun aux onglets de
            // cette page (groupe.html.twig) : de vrais evenements desormais,
            // avec la carte prevue pour ( _event_card.html.twig), plutot que
            // des evenements habilles en cartes de groupe.
            'similar' => $this->presenter->cards($this->events->findForListing(limit: 4)),
            // L'onglet « Evenements » du groupe affiche le meme calendrier :
            // il lui faut donc les memes variables. Meme limite que ci-dessus.
            ...$this->calendarData(),
            'avatars' => StaticEvents::avatars(),
        ]);
    }
}
