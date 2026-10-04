<?php

declare(strict_types=1);

namespace App\PrivateActivity\Controller;

use App\PrivateActivity\Entity\Photo;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Repository\AlbumRepository;
use App\PrivateActivity\Repository\PhotoRepository;
use App\PrivateActivity\Repository\PrivateActivityRepository;
use App\PrivateActivity\Security\PrivateActivityVoter;
use App\PrivateActivity\Service\AlbumService;
use App\Shared\Service\AccountIdentityPresenter;
use App\User\Entity\User;
use App\User\Presenter\AccountActivityPresenter;
use App\User\StaticAccount;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

/**
 * Album photo d'une activité privée (§4 de docs/corrections-client-2026-07-27.md).
 *
 * Menu « Mes albums photos » de l'espace compte (StaticAccount::menu()),
 * inerte jusqu'ici faute de back derrière : listing des activités où
 * l'utilisateur peut poster (organisateur ou participant ACCEPTÉ,
 * PrivateActivityVoter::VIEW_ALBUM), puis l'album de chacune.
 *
 * Le listing suit la maquette profil_album_particulier (30/09) ; la page
 * d'un album n'a pas encore de maquette.
 */
final class AlbumController extends AbstractController
{
    public function __construct(
        private readonly PrivateActivityRepository $activities,
        private readonly AlbumRepository $albums,
        private readonly PhotoRepository $photos,
        private readonly AlbumService $albumService,
        private readonly AccountIdentityPresenter $identity,
        private readonly AccountActivityPresenter $accountActivity,
        private readonly string $projectDir,
    ) {
    }

    private const PER_PAGE = 8;

    /**
     * Onglets Tous / Publics / Privés, recherche, tri, filtre « organisés /
     * rejoints », pagination par 8 — maquette profil_album_particulier.
     */
    #[Route(path: ['fr' => '/compte/albums', 'en' => '/en/account/albums'], name: 'app_account_albums')]
    public function index(Request $request): Response
    {
        $user = $this->currentUser();
        $all = $this->accountActivity->albums($user);

        $tab = (string) $request->query->get('onglet', 'tous');
        $query = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('tri', 'recents');
        $role = (string) $request->query->get('statut', '');
        $view = 'liste' === $request->query->get('vue') ? 'liste' : 'grille';

        $albums = array_values(array_filter($all, static function (array $album) use ($tab, $query, $role): bool {
            return match ($tab) {
                'publics' => $album['public'],
                'prives' => !$album['public'],
                default => true,
            }
            && ('' === $query || false !== mb_stripos($album['title'], $query))
            && match ($role) {
                'organises' => $album['organizer'],
                'rejoints' => !$album['organizer'],
                default => true,
            };
        }));

        usort($albums, match ($sort) {
            'anciens' => static fn (array $a, array $b): int => $a['date'] <=> $b['date'],
            'photos' => static fn (array $a, array $b): int => $b['photoCount'] <=> $a['photoCount'],
            default => static fn (array $a, array $b): int => $b['date'] <=> $a['date'],
        });

        $total = \count($albums);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($pages, max(1, $request->query->getInt('page', 1)));

        $photos = $this->photos->findBy(['author' => $user]);
        $bytes = 0;
        foreach ($photos as $photo) {
            $file = $this->projectDir.'/public/'.ltrim($photo->getPath(), '/');
            $bytes += is_file($file) ? (int) filesize($file) : 0;
        }

        return $this->render('private_activity/albums.html.twig', [
            'user' => $this->identity->identityFor($user),
            'menu' => StaticAccount::menu(),
            'active' => 'Mes albums photos',
            'albums' => \array_slice($albums, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => self::PER_PAGE,
            'tab' => $tab,
            'query' => $query,
            'sort' => $sort,
            'role' => $role,
            'view' => $view,
            'stats' => [
                'organized' => \count(array_filter($all, static fn (array $a): bool => $a['organizer'])),
                'joined' => \count(array_filter($all, static fn (array $a): bool => !$a['organizer'])),
                'photos' => \count($photos),
                'bytes' => $bytes,
            ],
            'max_photos' => AlbumService::MAX_PHOTOS_PER_ALBUM,
        ]);
    }

    #[Route(path: ['fr' => '/compte/albums/{id}', 'en' => '/en/account/albums/{id}'], name: 'app_account_album_show', methods: ['GET', 'POST'])]
    public function show(string $id, Request $request): Response
    {
        $activity = $this->findOrFail($id);
        $this->denyAccessUnlessGranted(PrivateActivityVoter::VIEW_ALBUM, $activity);
        $user = $this->currentUser();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

                return $this->redirectToRoute('app_account_album_show', ['id' => $id]);
            }

            $file = $request->files->get('photo');

            if (!$file instanceof UploadedFile) {
                $this->addFlash('error', 'Veuillez choisir une photo.');

                return $this->redirectToRoute('app_account_album_show', ['id' => $id]);
            }

            try {
                $album = $this->albumService->getOrCreate($activity);
                $this->albumService->addPhoto($album, $user, $file);
                $this->addFlash('success', 'Photo ajoutée à l\'album.');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_account_album_show', ['id' => $id]);
        }

        $album = $this->albums->findOneByActivity($activity);

        return $this->render('private_activity/album_show.html.twig', [
            'user' => $this->identity->identityFor($user),
            'menu' => StaticAccount::menu(),
            'active' => 'Mes albums photos',
            'activity' => $activity,
            'photos' => null !== $album ? $album->getPhotos() : [],
            'photo_count' => null !== $album ? $this->photos->countForAlbum($album) : 0,
            'max_photos' => AlbumService::MAX_PHOTOS_PER_ALBUM,
        ]);
    }

    #[Route(path: ['fr' => '/compte/albums/{id}/photos/{photoId}/supprimer', 'en' => '/en/account/albums/{id}/photos/{photoId}/delete'], name: 'app_account_album_photo_delete', methods: ['POST'])]
    public function deletePhoto(string $id, string $photoId, Request $request): Response
    {
        $activity = $this->findOrFail($id);
        $this->denyAccessUnlessGranted(PrivateActivityVoter::VIEW_ALBUM, $activity);
        $user = $this->currentUser();

        $album = $this->albums->findOneByActivity($activity);
        $photo = null !== $album ? $this->findPhotoOrFail($album->getPhotos()->toArray(), $photoId) : null;

        if (null === $photo) {
            throw new NotFoundHttpException('Cette photo est introuvable.');
        }

        // Sa propre photo, ou n'importe laquelle si on organise l'activité —
        // même logique que PrivateActivityVoter::MANAGE.
        if ($photo->getAuthor() !== $user && $activity->getOrganizer() !== $user) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return $this->redirectToRoute('app_account_album_show', ['id' => $id]);
        }

        $this->albumService->removePhoto($photo);
        $this->addFlash('success', 'Photo supprimée.');

        return $this->redirectToRoute('app_account_album_show', ['id' => $id]);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function findOrFail(string $id): PrivateActivity
    {
        $activity = Ulid::isValid($id) ? $this->activities->find(Ulid::fromString($id)) : null;

        if (null === $activity) {
            throw new NotFoundHttpException('Cette activité est introuvable.');
        }

        return $activity;
    }

    /**
     * @param list<Photo> $photos
     */
    private function findPhotoOrFail(array $photos, string $photoId): ?Photo
    {
        foreach ($photos as $photo) {
            if ((string) $photo->getId() === $photoId) {
                return $photo;
            }
        }

        return null;
    }
}
