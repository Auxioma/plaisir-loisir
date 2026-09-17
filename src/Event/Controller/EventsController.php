<?php

declare(strict_types=1);

namespace App\Event\Controller;

use App\Event\Entity\Event;
use App\Event\Entity\Group;
use App\Event\Entity\GroupAlbum;
use App\Event\Presenter\CalendarPresenter;
use App\Event\Presenter\EventPresenter;
use App\Event\Presenter\GroupPresenter;
use App\Event\Repository\EventRepository;
use App\Event\Repository\GroupAlbumRepository;
use App\Event\Repository\GroupRepository;
use App\Event\StaticEvents;
use App\I18n\Routing\LocaleUrlGenerator;
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

    #[Route(path: ['fr' => '/evenements', 'en' => '/en/events'], name: 'app_events')]
    public function index(): Response
    {
        return $this->render('event/nav/index.html.twig', [
            'events' => $this->presenter->cards($this->events->findForListing()),
            // Pastilles de navigation : une liste editoriale avec ses icones,
            // sans equivalent en base et sans compteur. Distincte des
            // categories qui colorent le badge des cartes.
            'categories' => StaticEvents::categories(),
            // Vignettes de visages decoratives, sans utilisateur derriere.
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
            'events' => $this->presenter->cards($this->events->findForListing()),
            'avatars' => StaticEvents::avatars(),
            'selections' => StaticEvents::selections(),
            'cities' => StaticEvents::cities(),
        ]);
    }

    #[Route(path: ['fr' => '/evenements/detail/{slug}', 'en' => '/en/events/detail/{slug}'], name: 'app_events_detail')]
    public function detail(string $slug): Response
    {
        $event = $this->findEventOrFail($slug);

        return $this->render('event/nav/detail.html.twig', [
            'event' => $this->presenter->card($event),
            // Roster decoratif : aucune entite d'inscription a un evenement
            // n'existe encore (voir Group::membersCount, meme situation), le
            // compte reel (event.participants) l'accompagne deja sur la fiche.
            'participants' => StaticEvents::participants(),
            // « Vous aimerez peut-etre aussi » : d'autres evenements reels,
            // le present exclu, plutot que la selection figee de la maquette.
            'similar' => $this->presenter->cards(array_filter(
                $this->events->findForListing(limit: 4 + 1),
                static fn (Event $e): bool => $e !== $event,
            )),
            'avatars' => StaticEvents::avatars(),
        ]);
    }

    #[Route(path: ['fr' => '/evenements/detail/{slug}/participants', 'en' => '/en/events/detail/{slug}/participants'], name: 'app_events_participants')]
    public function participants(string $slug): Response
    {
        $event = $this->findEventOrFail($slug);

        return $this->render('event/nav/participants.html.twig', [
            'event' => $this->presenter->card($event),
            'participants' => StaticEvents::participants(),
        ]);
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
