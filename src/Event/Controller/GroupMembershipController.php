<?php

declare(strict_types=1);

namespace App\Event\Controller;

use App\Event\Entity\Group;
use App\Event\Entity\GroupMembership;
use App\Event\Repository\GroupMembershipRepository;
use App\Event\Repository\GroupRepository;
use App\Event\Service\GroupMembershipService;
use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

/**
 * Adhésion aux groupes (09/11) : « Demander à rejoindre le groupe »,
 * annulation ou départ, et décision du créateur sur les demandes reçues.
 */
final class GroupMembershipController extends AbstractController
{
    public function __construct(
        private readonly GroupRepository $groups,
        private readonly GroupMembershipRepository $memberships,
        private readonly GroupMembershipService $service,
    ) {
    }

    #[Route(path: ['fr' => '/evenements/groupes/detail/{slug}/rejoindre', 'en' => '/en/events/groups/detail/{slug}/join'], name: 'app_group_join', methods: ['POST'])]
    public function join(string $slug, Request $request): Response
    {
        $group = $this->findGroupOrFail($slug);
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('info', 'Connectez-vous pour rejoindre ce groupe.');

            return $this->redirectToRoute('app_login');
        }
        if (!$this->isCsrfTokenValid('group_join', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return $this->redirectToRoute('app_group_detail', ['slug' => $slug]);
        }
        if ($group->getOwner() === $user) {
            $this->addFlash('info', 'Vous êtes le créateur de ce groupe.');

            return $this->redirectToRoute('app_group_detail', ['slug' => $slug]);
        }

        $existing = $this->memberships->findOneFor($group, $user);
        if (null !== $existing) {
            $wasPending = $existing->isPending();
            $this->service->leave($existing);
            $this->addFlash('success', $wasPending ? 'Votre demande a été annulée.' : sprintf('Vous avez quitté le groupe « %s ».', $group->getName()));

            return $this->redirectToRoute('app_group_detail', ['slug' => $slug]);
        }

        $membership = $this->service->request($group, $user);
        if ($membership->isPending()) {
            return $this->redirectToRoute('app_group_join_sent', ['groupe' => $slug]);
        }

        $this->addFlash('success', sprintf('Bienvenue dans le groupe « %s » !', $group->getName()));

        return $this->redirectToRoute('app_group_detail', ['slug' => $slug, 'onglet' => 'membres']);
    }

    #[Route(path: ['fr' => '/evenements/groupes/detail/{slug}/demandes/{id}/{decision}', 'en' => '/en/events/groups/detail/{slug}/requests/{id}/{decision}'], name: 'app_group_request_decide', requirements: ['decision' => 'accepter|refuser'], methods: ['POST'])]
    public function decide(string $slug, string $id, string $decision, Request $request): Response
    {
        $group = $this->findGroupOrFail($slug);
        $user = $this->getUser();
        if (!$user instanceof User || $group->getOwner() !== $user) {
            throw $this->createAccessDeniedException('Seul le créateur du groupe traite les demandes.');
        }
        if (!$this->isCsrfTokenValid('group_request', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return $this->redirectToRoute('app_group_detail', ['slug' => $slug, 'onglet' => 'membres']);
        }

        $membership = Ulid::isValid($id) ? $this->memberships->find(Ulid::fromString($id)) : null;
        if (!$membership instanceof GroupMembership || $membership->getGroup() !== $group || !$membership->isPending()) {
            throw new NotFoundHttpException('Cette demande est introuvable.');
        }

        if ('accepter' === $decision) {
            $this->service->accept($membership);
            $this->addFlash('success', 'Demande acceptée.');
        } else {
            $this->service->refuse($membership);
            $this->addFlash('success', 'Demande refusée.');
        }

        return $this->redirectToRoute('app_group_detail', ['slug' => $slug, 'onglet' => 'membres']);
    }

    private function findGroupOrFail(string $slug): Group
    {
        return $this->groups->findOneBySlug($slug) ?? throw new NotFoundHttpException('Ce groupe est introuvable.');
    }
}
