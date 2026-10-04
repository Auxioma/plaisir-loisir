<?php

declare(strict_types=1);

namespace App\Favorite\Controller;

use App\Event\Presenter\EventPresenter;
use App\Event\Presenter\GroupPresenter;
use App\Event\Repository\EventRepository;
use App\Event\StaticEvents;
use App\Favorite\Entity\Favorite;
use App\Favorite\Repository\FavoriteRepository;
use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * « Mes favoris » du menu principal — maquette docs/maquettes/favoris.jpeg
 * (01/10) : événements, groupes & clubs, activités privées et organisateurs
 * mis en favori. Les activités et destinations du catalogue restent dans
 * l'espace compte (/compte/favoris), qui suit sa propre maquette.
 */
#[IsGranted('ROLE_USER')]
final class FavoritesPageController extends AbstractController
{
    public function __construct(
        private readonly FavoriteRepository $favorites,
        private readonly EventPresenter $eventPresenter,
        private readonly GroupPresenter $groupPresenter,
        private readonly EventRepository $events,
    ) {
    }

    #[Route(path: ['fr' => '/mes-favoris', 'en' => '/en/my-favourites'], name: 'app_favorites_page')]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        $events = [];
        $groups = [];
        $activities = [];
        $organizers = [];

        foreach ($this->favorites->findBy(['user' => $user], ['createdAt' => 'DESC']) as $favorite) {
            /** @var Favorite $favorite */
            if (null !== $event = $favorite->getEvent()) {
                $events[] = $this->eventPresenter->card($event) + ['startsAt' => $event->getStartsAt()];
            } elseif (null !== $group = $favorite->getGroup()) {
                $groups[] = $this->groupPresenter->card($group);
            } elseif (null !== $activity = $favorite->getPrivateActivity()) {
                $activities[] = $activity;
            } elseif (null !== $organizer = $favorite->getOrganizer()) {
                $organizers[] = [
                    'user' => $organizer,
                    'name' => trim($organizer->getFirstName().' '.$organizer->getLastName()),
                    'events' => \count($this->events->findByOrganizer($organizer)),
                ];
            }
        }

        $tab = (string) $request->query->get('onglet', 'tous');
        if (!\in_array($tab, ['tous', 'evenements', 'groupes', 'activites', 'organisateurs'], true)) {
            $tab = 'tous';
        }

        return $this->render('favorite/index.html.twig', [
            'tab' => $tab,
            'events' => $events,
            'groups' => $groups,
            'activities' => $activities,
            'organizers' => $organizers,
            'avatars' => StaticEvents::avatars(),
        ]);
    }
}
