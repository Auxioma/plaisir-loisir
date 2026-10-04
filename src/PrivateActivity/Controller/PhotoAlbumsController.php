<?php

declare(strict_types=1);

namespace App\PrivateActivity\Controller;

use App\Event\Entity\GroupAlbum;
use App\Event\Repository\GroupAlbumRepository;
use App\Event\StaticEvents;
use App\PrivateActivity\Enum\ParticipationStatus;
use App\User\Entity\User;
use App\User\Presenter\AccountActivityPresenter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Album photo » du menu principal — maquette docs/maquettes/album_photo.jpeg
 * (01/10).
 *
 * Deux sources réunies :
 *  - « Événements publics » : les albums des groupes & clubs (GroupAlbum),
 *    ouverts à tous ;
 *  - « Événements privés » : les albums des activités privées que la
 *    personne connectée organise ou a rejointes (règle de
 *    PrivateActivityVoter::VIEW_ALBUM) — invisibles pour un visiteur.
 */
final class PhotoAlbumsController extends AbstractController
{
    private const PER_PAGE = 8;

    public function __construct(
        private readonly GroupAlbumRepository $groupAlbums,
        private readonly AccountActivityPresenter $accountActivity,
    ) {
    }

    #[Route(path: ['fr' => '/albums-photo', 'en' => '/en/photo-albums'], name: 'app_photo_albums')]
    public function index(Request $request): Response
    {
        $avatars = StaticEvents::avatars();
        $albums = [];

        foreach ($this->groupAlbums->findBy([], ['lastPhotoAt' => 'DESC']) as $album) {
            /** @var GroupAlbum $album */
            $group = $album->getGroup();
            $albums[] = [
                'title' => $album->getTitle(),
                'date' => $album->getLastPhotoAt(),
                'location' => $album->getLocation() ?? $group?->getLocation(),
                'image' => $album->getImagePath() ?? 'images/events/alb-seine.jpg',
                'photos' => $album->getPhotosCount(),
                'public' => true,
                'category' => 'Groupes & Clubs',
                'route' => null !== $group ? 'app_group_album' : null,
                'routeParams' => null !== $group ? ['groupSlug' => $group->getSlug(), 'albumId' => (string) $album->getId()] : [],
                'avatars' => \array_slice($avatars, 0, 4),
                'more' => max(0, ($group?->getMembersCount() ?? 0) - 4),
            ];
        }

        $user = $this->getUser();
        if ($user instanceof User) {
            foreach ($this->accountActivity->albums($user) as $album) {
                $activity = $album['activity'];
                $faces = [];
                $accepted = 0;
                foreach ($activity->getParticipations() as $participation) {
                    if (ParticipationStatus::Accepted === $participation->getStatus()) {
                        ++$accepted;
                        if (\count($faces) < 4) {
                            $faces[] = $participation->getParticipant()?->getAvatarPath() ?? 'images/account/avatar-default.svg';
                        }
                    }
                }

                $albums[] = [
                    'title' => $album['title'],
                    'date' => $album['date'],
                    'location' => $activity->getCity(),
                    'image' => $album['cover'] ?? 'images/events/ev-photo1.jpg',
                    'photos' => $album['photoCount'],
                    'public' => false,
                    'category' => $activity->getCategory()?->getName() ?? 'Autres',
                    'route' => 'app_account_album_show',
                    'routeParams' => ['id' => (string) $activity->getId()],
                    'avatars' => $faces,
                    'more' => max(0, $accepted - \count($faces)),
                ];
            }
        }

        $categories = [];
        foreach ($albums as $album) {
            $categories[$album['category']] = ($categories[$album['category']] ?? 0) + 1;
        }
        ksort($categories);

        $popular = $albums;
        usort($popular, static fn (array $a, array $b): int => $b['photos'] <=> $a['photos']);

        $tab = (string) $request->query->get('onglet', 'tous');
        $query = trim((string) $request->query->get('q', ''));
        $category = (string) $request->query->get('categorie', '');
        $period = (string) $request->query->get('periode', '');
        $sort = (string) $request->query->get('tri', 'recents');
        $since = match ($period) {
            '30j' => new \DateTimeImmutable('-30 days'),
            'annee' => new \DateTimeImmutable('first day of january this year 00:00'),
            default => null,
        };

        $filtered = array_values(array_filter($albums, static fn (array $a): bool => match ($tab) {
            'publics' => $a['public'],
            'prives' => !$a['public'],
            default => true,
        }
            && ('' === $category || $a['category'] === $category)
            && (null === $since || ($a['date'] ?? $since) >= $since)
            && ('' === $query || false !== mb_stripos($a['title'].' '.$a['location'], $query))));

        usort($filtered, match ($sort) {
            'anciens' => static fn (array $a, array $b): int => $a['date'] <=> $b['date'],
            'photos' => static fn (array $a, array $b): int => $b['photos'] <=> $a['photos'],
            default => static fn (array $a, array $b): int => $b['date'] <=> $a['date'],
        });

        $total = \count($filtered);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($pages, max(1, $request->query->getInt('page', 1)));

        return $this->render('private_activity/photo_albums.html.twig', [
            'albums' => \array_slice($filtered, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => self::PER_PAGE,
            'tab' => $tab,
            'query' => $query,
            'category' => $category,
            'period' => $period,
            'sort' => $sort,
            'categories' => $categories,
            'all_count' => \count($albums),
            'popular' => \array_slice($popular, 0, 4),
        ]);
    }
}
