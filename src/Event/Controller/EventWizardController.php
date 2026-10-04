<?php

declare(strict_types=1);

namespace App\Event\Controller;

use App\Event\Entity\Event;
use App\Event\Repository\EventCategoryRepository;
use App\Event\Repository\EventRepository;
use App\Event\Service\EventDraftService;
use App\Event\StaticEventWizard;
use App\User\Entity\User;
use App\User\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

/**
 * « Créer un événement » — maquettes docs/maquettes/creation_evenements
 * (04/10) : guide (étape 0), puis 8 étapes validées côté serveur une à une,
 * aperçu en direct, brouillon enregistrable, publication immédiate ou
 * programmée.
 *
 * Boutons d'en-tête (champ `action`) : « next » valide l'étape et avance,
 * « prev » conserve la saisie et recule, « draft » enregistre un brouillon
 * en base, « publish » (étape 8) publie après revalidation de tout.
 */
final class EventWizardController extends AbstractController
{
    public function __construct(
        private readonly EventDraftService $draft,
        private readonly EventCategoryRepository $categories,
        private readonly EventRepository $events,
        private readonly UserRepository $users,
    ) {
    }

    #[Route(path: ['fr' => '/evenements/creer', 'en' => '/en/events/create'], name: 'app_event_guide', methods: ['GET'])]
    public function guide(): Response
    {
        return $this->render('event/guide.html.twig', [
            'steps' => StaticEventWizard::steps(),
            'examples' => array_slice($this->events->findForListing(limit: 4), 0, 4),
        ]);
    }

    #[Route(path: ['fr' => '/evenements/creer/succes/{slug}', 'en' => '/en/events/create/success/{slug}'], name: 'app_event_create_success')]
    public function success(string $slug): Response
    {
        $event = $this->events->findOneBySlug($slug);
        if (null === $event || $event->getOrganizer() !== $this->getUser()) {
            throw $this->createNotFoundException();
        }

        return $this->render('event/succes.html.twig', ['event' => $event]);
    }

    #[Route(path: ['fr' => '/evenements/creer/reprendre/{slug}', 'en' => '/en/events/create/resume/{slug}'], name: 'app_event_resume')]
    public function resume(string $slug, Request $request): Response
    {
        $event = $this->events->findOneBySlug($slug);
        if (!$event instanceof Event || $event->getOrganizer() !== $this->getUser()) {
            throw $this->createNotFoundException();
        }
        $this->draft->load($request->getSession(), $event);

        return $this->redirectToRoute('app_event_create', ['etape' => 1]);
    }

    /** Recherche de membres à inviter (étape 7). */
    #[Route(path: ['fr' => '/evenements/creer/contacts', 'en' => '/en/events/create/contacts'], name: 'app_event_contacts', methods: ['GET'])]
    public function contacts(Request $request): JsonResponse
    {
        $me = $this->getUser();
        $q = trim((string) $request->query->get('q', ''));
        if (!$me instanceof User || mb_strlen($q) < 2) {
            return new JsonResponse([]);
        }

        $rows = $this->users->createQueryBuilder('u')
            ->andWhere('LOWER(u.firstName) LIKE :q OR LOWER(u.lastName) LIKE :q OR LOWER(u.email) LIKE :q OR LOWER(CONCAT(u.firstName, \' \', u.lastName)) LIKE :q')
            ->andWhere('u.id != :me')
            ->setParameter('q', '%'.mb_strtolower($q).'%')
            ->setParameter('me', $me->getId(), 'ulid')
            ->setMaxResults(8)
            ->getQuery()->getResult();

        return new JsonResponse(array_map(static fn (User $u): array => [
            'id' => (string) $u->getId(),
            'name' => trim($u->getFirstName().' '.$u->getLastName()),
            'avatar' => $u->getAvatarPath(),
        ], $rows));
    }

    #[Route(path: ['fr' => '/evenements/creer/{etape}', 'en' => '/en/events/create/{etape}'], name: 'app_event_create', requirements: ['etape' => '[1-8]'], methods: ['GET', 'POST'])]
    public function create(Request $request, int $etape): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }
        $session = $request->getSession();
        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('event_wizard', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Votre session a expiré, merci de recommencer cette étape.');

                return $this->redirectToRoute('app_event_create', ['etape' => $etape]);
            }

            $action = (string) $request->request->get('action', 'next');
            $errors = $this->draft->submitStep($session, $etape, $request);

            if ('prev' === $action) {
                return $this->redirectToRoute('app_event_create', ['etape' => max(1, $etape - 1)]);
            }

            if ('draft' === $action) {
                $current = $this->draft->current($session);
                if (mb_strlen((string) ($current['title'] ?? '')) < 3) {
                    $errors = ['title' => 'Donnez au moins un titre pour enregistrer un brouillon.'] + $errors;
                    $this->addFlash('error', 'Donnez au moins un titre pour enregistrer un brouillon.');
                } else {
                    $event = $this->draft->persist($session, $user, 'draft');
                    $this->addFlash('success', sprintf('Brouillon « %s » enregistré : retrouvez-le dans « Mes événements ».', $event->getTitle()));

                    return $this->redirectToRoute('app_event_create', ['etape' => $etape]);
                }
            } elseif ([] === $errors) {
                if (EventDraftService::STEPS !== $etape) {
                    return $this->redirectToRoute('app_event_create', ['etape' => $etape + 1]);
                }

                [$invalid, $stepErrors] = $this->draft->firstInvalidStep($this->draft->current($session));
                if (null !== $invalid) {
                    $this->addFlash('error', sprintf('Complétez l’étape %d avant de publier : %s', $invalid, reset($stepErrors)));

                    return $this->redirectToRoute('app_event_create', ['etape' => $invalid]);
                }

                $mode = (string) $this->draft->current($session)['publish_mode'];
                $event = $this->draft->persist($session, $user, \in_array($mode, ['now', 'scheduled', 'draft'], true) ? $mode : 'now');
                $this->addFlash('success', match ($mode) {
                    'draft' => sprintf('Brouillon « %s » enregistré.', $event->getTitle()),
                    'scheduled' => sprintf('« %s » sera publié le %s.', $event->getTitle(), $event->getPublishAt()?->format('d/m/Y à H:i')),
                    default => sprintf('Votre événement « %s » est publié !', $event->getTitle()),
                });

                return $this->redirectToRoute('app_event_create_success', ['slug' => $event->getSlug()]);
            }
        } elseif ($etape > $this->draft->maxReachable($session)) {
            // On ne saute pas d'étape : retour à la première non validée.
            return $this->redirectToRoute('app_event_create', ['etape' => $this->draft->maxReachable($session)]);
        }

        $draft = $this->draft->current($session);
        $categories = $this->categories->findBy([], ['position' => 'ASC']);
        $looks = StaticEventWizard::categoryLooks();

        return $this->render('event/creer.html.twig', [
            'step' => $etape,
            'steps' => StaticEventWizard::steps(),
            'advice' => StaticEventWizard::advice($etape),
            'types' => StaticEventWizard::types(),
            'categories' => array_values(array_filter($categories, static fn ($c): bool => isset($looks[$c->getSlug()]))),
            'category_looks' => $looks,
            'reminders' => StaticEventWizard::reminders(),
            'timezones' => StaticEventWizard::timezones(),
            'capacity_options' => StaticEventWizard::capacityOptions(),
            'draft' => $draft,
            'done' => (array) $draft['done'],
            'errors' => $errors,
            'me' => $user,
            'invited' => array_values(array_filter(array_map(fn (string $id): ?User => Ulid::isValid($id) ? $this->users->find(Ulid::fromString($id)) : null, (array) $draft['invites']))),
            'category' => $this->categories->findOneBy(['slug' => (string) ($draft['category'] ?? '')]),
        ], new Response(null, [] === $errors ? 200 : 422));
    }
}
