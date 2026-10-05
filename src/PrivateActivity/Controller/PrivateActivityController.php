<?php

declare(strict_types=1);

namespace App\PrivateActivity\Controller;

use App\Catalog\Repository\CategoryRepository;
use App\PrivateActivity\Entity\Participation;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\ParticipationStatus;
use App\PrivateActivity\Repository\ParticipationRepository;
use App\PrivateActivity\Repository\PrivateActivityRepository;
use App\PrivateActivity\Security\ParticipationVoter;
use App\PrivateActivity\Security\PrivateActivityVoter;
use App\PrivateActivity\Service\PrivateActivityDraftService;
use App\PrivateActivity\Service\PrivateActivityService;
use App\Shared\Service\AccountIdentityPresenter;
use App\User\Entity\User;
use App\User\Presenter\AccountActivityPresenter;
use App\User\StaticAccount;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

/**
 * Activités privées entre particuliers (§12-13 du CDC) : découverte
 * publique, demande de participation, gestion côté organisateur.
 *
 * UN SEUL CONTRÔLEUR POUR VISITEUR, PARTICIPANT ET ORGANISATEUR
 * Contrairement à Provider/Quote (deux publics, deux applications), la fiche
 * d'une activité privée est LA MÊME PAGE pour les trois : ce qui change, ce
 * sont les blocs qu'elle affiche (bouton participer, lieu exact, panneau de
 * gestion), décidés par les Voters. Deux contrôleurs auraient dupliqué le
 * calcul de la page pour un gain de lisibilité nul — l'écran est un seul et
 * même gabarit dans la maquette d'un tel produit.
 */
final class PrivateActivityController extends AbstractController
{
    public function __construct(
        private readonly PrivateActivityRepository $activities,
        private readonly ParticipationRepository $participations,
        private readonly CategoryRepository $categories,
        private readonly PrivateActivityService $service,
        private readonly AccountIdentityPresenter $identity,
        private readonly AccountActivityPresenter $accountActivity,
    ) {
    }

    #[Route(path: ['fr' => '/activites-privees', 'en' => '/en/private-activities'], name: 'app_private_activities')]
    public function index(Request $request): Response
    {
        $categorySlug = (string) $request->query->get('metier', '');
        $category = '' !== $categorySlug ? $this->categories->findOneBy(['slug' => $categorySlug]) : null;
        $place = trim((string) $request->query->get('lieu', ''));
        $keywords = trim((string) $request->query->get('q', ''));
        $date = (string) $request->query->get('date', '');
        $day = preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date) ? (\DateTimeImmutable::createFromFormat('!Y-m-d', $date) ?: null) : null;

        // Activités à venir seulement (05/10) : une sortie passée ne se rejoint plus.
        $results = $this->activities->findUpcomingDiscoverable($this->isGranted('ROLE_USER'), $category, $place, $keywords, $day);

        return $this->render('private_activity/index.html.twig', [
            'activities' => $results,
            'categories' => $this->categories->findRoots(),
            'selected_category' => $category,
            'filters' => ['lieu' => $place, 'q' => $keywords, 'date' => null !== $day ? $date : ''],
        ]);
    }

    #[Route(path: ['fr' => '/activites-privees/{id}', 'en' => '/en/private-activities/{id}'], name: 'app_private_activity_show')]
    public function show(string $id): Response
    {
        $activity = $this->findOrFail($id);
        $this->denyAccessUnlessGranted(PrivateActivityVoter::VIEW, $activity);

        $user = $this->getUser();
        $myParticipation = $user instanceof User
            ? $this->participations->findOneByActivityAndParticipant($activity, $user)
            : null;

        return $this->render('private_activity/show.html.twig', [
            'activity' => $activity,
            'my_participation' => $myParticipation,
            'can_participate' => null === $myParticipation && $this->isGranted(PrivateActivityVoter::PARTICIPATE, $activity),
            'can_manage' => $this->isGranted(PrivateActivityVoter::MANAGE, $activity),
            'can_view_exact_location' => $this->isGranted(PrivateActivityVoter::VIEW_EXACT_LOCATION, $activity),
            'pending' => array_filter($activity->getParticipations()->toArray(), static fn (Participation $p): bool => ParticipationStatus::Pending === $p->getStatus()),
            'accepted' => array_filter($activity->getParticipations()->toArray(), static fn (Participation $p): bool => ParticipationStatus::Accepted === $p->getStatus()),
            'waiting' => array_filter($activity->getParticipations()->toArray(), static fn (Participation $p): bool => ParticipationStatus::WaitingList === $p->getStatus()),
        ]);
    }

    #[Route(path: ['fr' => '/activites-privees/{id}/participer', 'en' => '/en/private-activities/{id}/participate'], name: 'app_private_activity_participate', methods: ['POST'])]
    public function participate(string $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $activity = $this->findOrFail($id);
        $this->denyAccessUnlessGranted(PrivateActivityVoter::PARTICIPATE, $activity);

        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return $this->redirectToRoute('app_private_activity_show', ['id' => $id]);
        }

        try {
            $participation = $this->service->requestParticipation($activity, $this->currentUser());

            $this->addFlash('success', match ($participation->getStatus()) {
                ParticipationStatus::Accepted => 'Votre place est confirmée !',
                ParticipationStatus::WaitingList => 'L\'activité est complète : vous êtes sur liste d\'attente, vous serez prévenu en cas de désistement.',
                default => 'Votre demande a été envoyée à l\'organisateur.',
            });
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_private_activity_show', ['id' => $id]);
    }

    /**
     * « Mes activités créées » — maquette profil_activites_particulier (30/09) :
     * les activités privées organisées par l'utilisateur. Celles qu'il a
     * rejointes sont suivies dans « Mes réservations ».
     *
     * Les onglets de la maquette (En attente, Brouillons) n'ont pas
     * d'équivalent : une activité privée est publiée dès sa création. Ils
     * sont remplacés par les états réels (Complètes, Annulées).
     */
    #[Route(path: ['fr' => '/compte/activites-privees', 'en' => '/en/account/private-activities'], name: 'app_account_private_activities')]
    public function myActivities(Request $request): Response
    {
        $user = $this->currentUser();
        $all = $this->accountActivity->createdActivities($user);

        $tabs = ['toutes' => null, 'en-ligne' => 'online', 'brouillons' => 'draft', 'completes' => 'full', 'passees' => 'past', 'annulees' => 'cancelled'];
        $tab = (string) $request->query->get('onglet', 'toutes');
        if (!\array_key_exists($tab, $tabs)) {
            $tab = 'toutes';
        }
        $query = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('tri', 'recentes');

        $rows = array_values(array_filter($all, static fn (array $row): bool => (null === $tabs[$tab] || $row['status'] === $tabs[$tab])
            && ('' === $query || false !== mb_stripos($row['title'].' '.$row['place'], $query))));

        usort($rows, match ($sort) {
            'anciennes' => static fn (array $a, array $b): int => $a['date'] <=> $b['date'],
            'participants' => static fn (array $a, array $b): int => $b['participants'] <=> $a['participants'],
            default => static fn (array $a, array $b): int => $b['date'] <=> $a['date'],
        });

        $perPage = 6;
        $total = \count($rows);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, $request->query->getInt('page', 1)));

        $count = static fn (string $status): int => \count(array_filter($all, static fn (array $r): bool => $r['status'] === $status));
        $participants = array_sum(array_column($all, 'participants'));
        $capacity = array_sum(array_map(static fn (array $r): int => $r['capacity'] ?? 0, $all));
        $monthStart = new \DateTimeImmutable('first day of this month 00:00');

        return $this->render('private_activity/mes_activites.html.twig', [
            'user' => $this->identity->identityFor($user),
            'menu' => StaticAccount::menu(),
            'active' => 'Mes activités créées',
            'rows' => \array_slice($rows, ($page - 1) * $perPage, $perPage),
            'tab' => $tab,
            'query' => $query,
            'sort' => $sort,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'per_page' => $perPage,
            'counts' => [
                'all' => \count($all),
                'online' => $count('online'),
                'full' => $count('full'),
                'cancelled' => $count('cancelled'),
                'past' => $count('past'),
                'month' => \count(array_filter($all, static fn (array $r): bool => $r['activity']->getCreatedAt() >= $monthStart)),
            ],
            'global' => [
                'participants' => $participants,
                'pending' => array_sum(array_column($all, 'pending')),
                'fill' => $capacity > 0 ? (int) round($participants * 100 / $capacity) : null,
            ],
        ]);
    }

    /**
     * « Organiser une activité » : assistant en 5 étapes (05/10), qui
     * remplace le formulaire d'une page (sans photo ni lieu géolocalisé).
     */
    #[Route(path: ['fr' => '/compte/activites-privees/nouvelle', 'en' => '/en/account/private-activities/new'], name: 'app_account_private_activities_new', methods: ['GET'])]
    public function new(Request $request, PrivateActivityDraftService $drafts): Response
    {
        if ($request->query->getBoolean('vierge')) {
            $drafts->clear($request->getSession());
        }

        return $this->redirectToRoute('app_account_private_activity_wizard', ['etape' => 1]);
    }

    /** Reprendre un brouillon dans l'assistant (05/10). */
    #[Route(path: ['fr' => '/compte/activites-privees/{id}/reprendre', 'en' => '/en/account/private-activities/{id}/resume'], name: 'app_account_private_activity_resume', methods: ['GET'])]
    public function resume(string $id, Request $request, PrivateActivityDraftService $drafts): Response
    {
        $activity = $this->findOrFail($id);
        $this->denyAccessUnlessGranted(PrivateActivityVoter::MANAGE, $activity);
        $drafts->load($request->getSession(), $activity);

        return $this->redirectToRoute('app_account_private_activity_wizard', ['etape' => 1]);
    }

    #[Route(path: ['fr' => '/compte/activites-privees/creer/{etape}', 'en' => '/en/account/private-activities/create/{etape}'], name: 'app_account_private_activity_wizard', requirements: ['etape' => '[1-5]'], methods: ['GET', 'POST'])]
    public function wizard(int $etape, Request $request, PrivateActivityDraftService $drafts): Response
    {
        $session = $request->getSession();
        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('private_activity_wizard', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Votre session a expiré, merci de recommencer cette étape.');

                return $this->redirectToRoute('app_account_private_activity_wizard', ['etape' => $etape]);
            }
            $action = (string) $request->request->get('action', 'next');
            $errors = $drafts->submitStep($session, $etape, $request);

            if ('prev' === $action) {
                return $this->redirectToRoute('app_account_private_activity_wizard', ['etape' => max(1, $etape - 1)]);
            }
            // Brouillon (05/10) : la saisie est gardée en base, même incomplète.
            if ('draft' === $action) {
                try {
                    $activity = $drafts->saveDraft($session, $drafts->current($session), $this->currentUser());
                    $this->addFlash('success', sprintf('Brouillon « %s » enregistré : reprenez-le quand vous voulez depuis « Mes activités créées ».', $activity->getTitle()));

                    return $this->redirectToRoute('app_account_private_activity_wizard', ['etape' => $etape]);
                } catch (\InvalidArgumentException $e) {
                    $this->addFlash('error', $e->getMessage());
                }
            } elseif ([] === $errors) {
                if (PrivateActivityDraftService::STEPS !== $etape) {
                    return $this->redirectToRoute('app_account_private_activity_wizard', ['etape' => $etape + 1]);
                }
                [$invalid, $stepErrors] = $drafts->firstInvalidStep($drafts->current($session));
                if (null !== $invalid) {
                    $this->addFlash('error', sprintf('Complétez l’étape %d avant de publier : %s', $invalid, reset($stepErrors)));

                    return $this->redirectToRoute('app_account_private_activity_wizard', ['etape' => $invalid]);
                }
                $activity = $drafts->publish($drafts->current($session), $this->currentUser());
                $drafts->clear($session);
                $this->addFlash('success', 'Votre activité est publiée ! Partagez-la pour réunir vos participants.');

                return $this->redirectToRoute('app_private_activity_show', ['id' => (string) $activity->getId()]);
            }
        } elseif ($etape > $drafts->maxReachable($session)) {
            return $this->redirectToRoute('app_account_private_activity_wizard', ['etape' => $drafts->maxReachable($session)]);
        }

        $draft = $drafts->current($session);

        return $this->render('private_activity/wizard.html.twig', [
            'user' => $this->identity->identityFor($this->currentUser()),
            'menu' => StaticAccount::menu(),
            'active' => 'Mes activités créées',
            'step' => $etape,
            'steps' => [
                ['title' => 'L’activité', 'subtitle' => 'Titre, catégorie, description'],
                ['title' => 'Date et lieu', 'subtitle' => 'Quand et où'],
                ['title' => 'Photo et infos', 'subtitle' => 'Image, à apporter'],
                ['title' => 'Participants', 'subtitle' => 'Nombre, inscription, visibilité'],
                ['title' => 'Publication', 'subtitle' => 'Vérifier et publier'],
            ],
            'draft' => $draft,
            'done' => (array) $draft['done'],
            'errors' => $errors,
            'categories' => $this->categories->findRoots(),
            'category' => $this->categories->findOneBy(['slug' => (string) ($draft['category'] ?? '')]),
            'all_categories' => $this->categories->findRoots(),
        ], new Response(null, [] === $errors ? 200 : 422));
    }

    #[Route(path: ['fr' => '/compte/activites-privees/{id}/annuler', 'en' => '/en/account/private-activities/{id}/cancel'], name: 'app_account_private_activity_cancel', methods: ['POST'])]
    public function cancel(string $id, Request $request): Response
    {
        $activity = $this->findOrFail($id);
        $this->denyAccessUnlessGranted(PrivateActivityVoter::MANAGE, $activity);

        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return $this->redirectToRoute('app_private_activity_show', ['id' => $id]);
        }

        $this->service->cancel($activity, $this->currentUser());
        $this->addFlash('success', 'Activité annulée.');

        return $this->redirectToRoute('app_private_activity_show', ['id' => $id]);
    }

    #[Route(path: ['fr' => '/compte/activites-privees/{id}/participations/{participationId}/decider', 'en' => '/en/account/private-activities/{id}/participations/{participationId}/decide'], name: 'app_account_private_activity_decide', methods: ['POST'])]
    public function decide(string $id, string $participationId, Request $request): Response
    {
        $activity = $this->findOrFail($id);
        $participation = $this->findParticipationOrFail($activity, $participationId);
        $this->denyAccessUnlessGranted(ParticipationVoter::DECIDE, $participation);

        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return $this->redirectToRoute('app_private_activity_show', ['id' => $id]);
        }

        $accept = 'accepter' === (string) $request->request->get('decision');

        try {
            $this->service->decide($participation, $this->currentUser(), $accept);
            $this->addFlash('success', $accept ? 'Demande acceptée.' : 'Demande refusée.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_private_activity_show', ['id' => $id]);
    }

    #[Route(path: ['fr' => '/compte/activites-privees/{id}/participations/{participationId}/annuler', 'en' => '/en/account/private-activities/{id}/participations/{participationId}/cancel'], name: 'app_account_private_activity_participation_cancel', methods: ['POST'])]
    public function cancelParticipation(string $id, string $participationId, Request $request): Response
    {
        $activity = $this->findOrFail($id);
        $participation = $this->findParticipationOrFail($activity, $participationId);
        $this->denyAccessUnlessGranted(ParticipationVoter::CANCEL, $participation);

        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return $this->redirectToRoute('app_private_activity_show', ['id' => $id]);
        }

        $this->service->cancelParticipation($participation, $this->currentUser());
        $this->addFlash('success', 'Votre participation a été annulée.');

        return $this->redirectToRoute('app_private_activity_show', ['id' => $id]);
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

    private function findParticipationOrFail(PrivateActivity $activity, string $participationId): Participation
    {
        foreach ($activity->getParticipations() as $participation) {
            if ((string) $participation->getId() === $participationId) {
                return $participation;
            }
        }

        throw new NotFoundHttpException('Cette participation est introuvable.');
    }
}
