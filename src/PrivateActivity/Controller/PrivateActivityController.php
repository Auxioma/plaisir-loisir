<?php

declare(strict_types=1);

namespace App\PrivateActivity\Controller;

use App\Catalog\Repository\CategoryRepository;
use App\PrivateActivity\Entity\Participation;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\ParticipationMode;
use App\PrivateActivity\Enum\ParticipationStatus;
use App\PrivateActivity\Enum\PrivateActivityVisibility;
use App\PrivateActivity\Repository\ParticipationRepository;
use App\PrivateActivity\Repository\PrivateActivityRepository;
use App\PrivateActivity\Security\ParticipationVoter;
use App\PrivateActivity\Security\PrivateActivityVoter;
use App\PrivateActivity\Service\PrivateActivityService;
use App\User\Entity\User;
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
    ) {
    }

    #[Route(path: ['fr' => '/activites-privees', 'en' => '/en/private-activities'], name: 'app_private_activities')]
    public function index(Request $request): Response
    {
        $categorySlug = (string) $request->query->get('metier', '');
        $category = '' !== $categorySlug ? $this->categories->findOneBy(['slug' => $categorySlug]) : null;

        $results = $this->isGranted('ROLE_USER')
            ? $this->activities->findVisibleToMembers($category)
            : $this->activities->findPublic($category);

        return $this->render('private_activity/index.html.twig', [
            'activities' => $results,
            'categories' => $this->categories->findRoots(),
            'selected_category' => $category,
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

    #[Route(path: ['fr' => '/compte/activites-privees', 'en' => '/en/account/private-activities'], name: 'app_account_private_activities')]
    public function myActivities(): Response
    {
        $user = $this->currentUser();

        return $this->render('private_activity/mes_activites.html.twig', [
            'user' => $this->accountUser(),
            'menu' => StaticAccount::menu(),
            'active' => 'Mes activités privées',
            'organized' => $this->activities->findByOrganizer($user),
            'joined' => array_filter($this->participations->findByParticipant($user), static fn (Participation $p): bool => ParticipationStatus::Cancelled !== $p->getStatus()),
        ]);
    }

    #[Route(path: ['fr' => '/compte/activites-privees/nouvelle', 'en' => '/en/account/private-activities/new'], name: 'app_account_private_activities_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Votre session a expiré, merci de renvoyer le formulaire.');

                return $this->redirectToRoute('app_account_private_activities_new');
            }

            $categorySlug = (string) $request->request->get('metier', '');
            $title = trim((string) $request->request->get('titre', ''));
            $description = trim((string) $request->request->get('description', ''));
            $city = trim((string) $request->request->get('ville', ''));
            $location = trim((string) $request->request->get('lieu', ''));
            $scheduledAtRaw = trim((string) $request->request->get('date', ''));
            $minRaw = trim((string) $request->request->get('min', ''));
            $maxRaw = trim((string) $request->request->get('max', ''));
            $visibility = PrivateActivityVisibility::tryFrom((string) $request->request->get('visibilite', '')) ?? PrivateActivityVisibility::Public;
            $participationMode = ParticipationMode::tryFrom((string) $request->request->get('mode', '')) ?? ParticipationMode::Validation;
            $showExactAddress = $request->request->getBoolean('afficher_adresse', true);

            $category = '' !== $categorySlug ? $this->categories->findOneBy(['slug' => $categorySlug]) : null;

            $errors = [];
            if (null === $category) {
                $errors[] = 'Veuillez choisir une catégorie.';
            }
            if ('' === $title) {
                $errors[] = 'Veuillez donner un titre à votre activité.';
            }

            $scheduledAt = null;
            if ('' !== $scheduledAtRaw) {
                try {
                    $scheduledAt = new \DateTimeImmutable($scheduledAtRaw);
                } catch (\Exception) {
                    $errors[] = 'La date saisie n\'est pas valide.';
                }
            }

            if ([] === $errors) {
                $activity = $this->service->create(
                    organizer: $this->currentUser(),
                    title: mb_substr($title, 0, 150),
                    category: $category,
                    description: '' !== $description ? $description : null,
                    scheduledAt: $scheduledAt,
                    city: '' !== $city ? $city : null,
                    location: '' !== $location ? $location : null,
                    showExactAddress: $showExactAddress,
                    visibility: $visibility,
                    participationMode: $participationMode,
                    minParticipants: ctype_digit($minRaw) ? (int) $minRaw : null,
                    maxParticipants: ctype_digit($maxRaw) ? (int) $maxRaw : null,
                );

                $this->addFlash('success', 'Votre activité a été publiée.');

                return $this->redirectToRoute('app_private_activity_show', ['id' => (string) $activity->getId()]);
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('private_activity/nouvelle.html.twig', [
            'user' => $this->accountUser(),
            'menu' => StaticAccount::menu(),
            'active' => 'Mes activités privées',
            'categories' => $this->categories->findRoots(),
            'visibilities' => PrivateActivityVisibility::cases(),
            'modes' => ParticipationMode::cases(),
        ]);
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

    /**
     * @return array{name: string, firstName: string, email: string, avatar: string, memberSince: string, unread: int}
     */
    private function accountUser(): array
    {
        $user = $this->currentUser();
        $firstName = $user->getFirstName();
        $fullName = trim($firstName.' '.$user->getLastName());
        $demo = StaticAccount::user();

        return [
            'name' => '' !== $fullName ? $fullName : $user->getEmail(),
            'firstName' => '' !== $firstName ? $firstName : $user->getLastName(),
            'email' => $user->getEmail(),
            'avatar' => $demo['avatar'],
            'memberSince' => $this->formatMemberSince($user->getCreatedAt()),
            'unread' => $demo['unread'],
        ];
    }

    private function formatMemberSince(?\DateTimeImmutable $createdAt): string
    {
        if (null === $createdAt) {
            return '';
        }

        $formatted = (string) \IntlDateFormatter::formatObject($createdAt, 'LLLL y', \Locale::getDefault());

        return mb_strtoupper(mb_substr($formatted, 0, 1)).mb_substr($formatted, 1);
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
