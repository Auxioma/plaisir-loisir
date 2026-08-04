<?php

declare(strict_types=1);

namespace App\Event\Controller;

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
    #[Route('/evenements', name: 'app_events')]
    public function index(): Response
    {
        return $this->render('event/nav/index.html.twig', [
            'events' => StaticEvents::events(),
            'categories' => StaticEvents::categories(),
            'avatars' => StaticEvents::avatars(),
        ]);
    }

    #[Route('/evenements/tous', name: 'app_events_all')]
    public function all(): Response
    {
        return $this->render('event/nav/tous.html.twig', [
            'events' => StaticEvents::eventsListing(),
            'events_filtered' => StaticEvents::eventsFiltered(),
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
            'events' => StaticEvents::eventsListing(),
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
            'groups' => StaticEvents::groups(),
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
            'group_events' => StaticEvents::groupEvents(),
            'members' => StaticEvents::members(),
            'albums' => StaticEvents::albums(),
            'calendar' => StaticEvents::calendar(),
            'avatars' => StaticEvents::avatars(),
        ]);
    }
}
