<?php

declare(strict_types=1);

namespace App\Event\Controller;

use App\Event\Entity\Group;
use App\Event\Presenter\EventPresenter;
use App\Event\Presenter\GroupPresenter;
use App\Event\Repository\EventRepository;
use App\Event\Repository\GroupAlbumRepository;
use App\Event\Repository\GroupRepository;
use App\Event\StaticEvents;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
    ) {
    }

    /**
     * Le groupe dont la maquette montre la page de detail.
     *
     * Il n'y a qu'un seul ecran de detail de groupe, et l'adresse n'en designe
     * aucun : « /evenements/groupes/detail/{onglet} » ne porte pas de slug. On
     * sert donc le premier groupe, celui qui porte les albums. Le jour ou
     * l'adresse designera un groupe, cette methode disparaitra.
     */
    private function firstGroup(): Group
    {
        $group = $this->groups->findForListing(1)[0] ?? null;

        if (null === $group) {
            throw $this->createNotFoundException('Aucun groupe en base.');
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

    #[Route('/evenements', name: 'app_events')]
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

    #[Route('/evenements/tous', name: 'app_events_all')]
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

    #[Route('/evenements/calendrier', name: 'app_events_calendar')]
    public function calendar(): Response
    {
        return $this->render('event/nav/calendrier.html.twig', [
            'calendar' => StaticEvents::calendar(),
            'selections' => StaticEvents::selections(),
            'cities' => StaticEvents::cities(),
        ]);
    }

    #[Route('/evenements/prives', name: 'app_events_private')]
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

    #[Route('/evenements/detail', name: 'app_events_detail')]
    public function detail(): Response
    {
        return $this->render('event/nav/detail.html.twig', [
            'participants' => StaticEvents::participants(),
            'similar' => StaticEvents::similar(),
            'avatars' => StaticEvents::avatars(),
        ]);
    }

    #[Route('/evenements/detail/participants', name: 'app_events_participants')]
    public function participants(): Response
    {
        return $this->render('event/nav/participants.html.twig', [
            'participants' => StaticEvents::participants(),
        ]);
    }

    #[Route('/evenements/groupes', name: 'app_groups')]
    public function groups(): Response
    {
        return $this->render('event/nav/groupes.html.twig', [
            'groups' => $this->groupPresenter->cards($this->groups->findForListing()),
            'avatars' => StaticEvents::avatars(),
        ]);
    }

    #[Route('/evenements/groupes/detail/photos/album', name: 'app_group_album')]
    public function album(): Response
    {
        return $this->render('event/nav/album.html.twig', [
            'similar' => StaticEvents::similar(),
            'avatars' => StaticEvents::avatars(),
        ]);
    }

    #[Route('/evenements/groupes/detail/demande-envoyee', name: 'app_group_join_sent')]
    public function joinSent(): Response
    {
        return $this->render('event/nav/demande.html.twig');
    }

    #[Route('/evenements/groupes/detail/{onglet}', name: 'app_group_detail', requirements: ['onglet' => 'apropos|evenements|membres|photos|discussions'], defaults: ['onglet' => 'apropos'])]
    public function groupDetail(string $onglet): Response
    {
        return $this->render('event/nav/groupe.html.twig', [
            'tab' => $onglet,
            'similar' => StaticEvents::similar(),
            // « Evenements » et « Groupes similaires » affichent des EVENEMENTS
            // avec la mise en page des cartes de groupe, et un texte de
            // remplissage. Ils restent statiques : les recomposer supposerait
            // de stocker du lorem ipsum en base.
            'group_events' => StaticEvents::groupEvents(),
            // Les membres sont des prenoms et des photos de la maquette, sans
            // compte derriere : il n'y a rien a brancher tant que l'adhesion a
            // un groupe n'existe pas.
            'members' => StaticEvents::members(),
            'albums' => $this->groupPresenter->albums($this->albums->findForGroup($this->firstGroup())),
            'calendar' => StaticEvents::calendar(),
            'avatars' => StaticEvents::avatars(),
        ]);
    }
}
