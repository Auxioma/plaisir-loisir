<?php

declare(strict_types=1);

namespace App\Messaging\Controller;

use App\Messaging\Entity\Conversation;
use App\Messaging\Repository\ConversationRepository;
use App\Messaging\Security\ConversationVoter;
use App\Messaging\Service\MessagingService;
use App\Provider\Repository\ProviderProfileRepository;
use App\Shared\Service\AccountIdentityPresenter;
use App\User\Entity\User;
use App\User\StaticAccount;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

/**
 * Messagerie client ↔ professionnel (§14 du CDC).
 *
 * Un seul jeu de routes pour les deux côtés : une conversation n'appartient
 * pas plus au client qu'à l'annonceur, contrairement à ServiceRequest/Quote
 * (voir ServiceRequestController/ProviderRequestController, séparés parce que
 * les DROITS y sont asymétriques). Le menu affiché (client ou pro) suit
 * simplement le rôle du compte connecté.
 *
 * MessagingService (ouverture de fil, envoi, marquage lu) existait déjà,
 * testé, depuis l'audit du 7 septembre : il ne manquait que ces écrans.
 */
#[IsGranted('ROLE_USER')]
final class ConversationController extends AbstractController
{
    public function __construct(
        private readonly ConversationRepository $conversations,
        private readonly MessagingService $messagingService,
        private readonly ProviderProfileRepository $providerProfiles,
        private readonly AccountIdentityPresenter $identity,
    ) {
    }

    #[Route(path: ['fr' => '/compte/messages', 'en' => '/en/account/messages'], name: 'app_account_messages')]
    public function index(): Response
    {
        $user = $this->currentUser();
        $conversations = $this->conversations->findForUser($user);

        usort(
            $conversations,
            fn (Conversation $a, Conversation $b): int => $this->lastActivity($b) <=> $this->lastActivity($a),
        );

        $rows = array_map(fn (Conversation $conversation): array => [
            'conversation' => $conversation,
            'other' => $this->otherPartyLabel($conversation, $user),
            'last_message' => $conversation->getMessages()->last() ?: null,
            'unread' => $this->hasUnreadFor($conversation, $user),
        ], $conversations);

        return $this->render('messaging/liste.html.twig', [
            'user' => $this->identity->identityFor($user),
            'menu' => $this->menuFor($user),
            'active' => 'Messages',
            'rows' => $rows,
        ]);
    }

    #[Route(path: ['fr' => '/compte/messages/{id}', 'en' => '/en/account/messages/{id}'], name: 'app_account_messages_show', methods: ['GET', 'POST'])]
    public function show(string $id, Request $request): Response
    {
        $conversation = $this->findOrFail($id);
        $this->denyAccessUnlessGranted(ConversationVoter::VIEW, $conversation);

        $user = $this->currentUser();

        if ($request->isMethod('POST')) {
            $this->denyAccessUnlessGranted(ConversationVoter::REPLY, $conversation);

            if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

                return $this->redirectToRoute('app_account_messages_show', ['id' => $id]);
            }

            $body = trim((string) $request->request->get('message', ''));
            if ('' !== $body) {
                $this->messagingService->sendMessage($conversation, $user, mb_substr($body, 0, 4000));
            }

            return $this->redirectToRoute('app_account_messages_show', ['id' => $id]);
        }

        $this->messagingService->markRead($conversation, $user);

        return $this->render('messaging/conversation.html.twig', [
            'user' => $this->identity->identityFor($user),
            'menu' => $this->menuFor($user),
            'active' => 'Messages',
            'conversation' => $conversation,
            'other' => $this->otherPartyLabel($conversation, $user),
            'current_user' => $user,
        ]);
    }

    /**
     * Ouvre (ou retrouve) une conversation avec un professionnel, depuis sa
     * fiche publique, puis redirige vers le fil.
     */
    #[Route(path: ['fr' => '/compte/messages/nouvelle/{slug}', 'en' => '/en/account/messages/new/{slug}'], name: 'app_account_messages_start', methods: ['POST'])]
    public function start(string $slug, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return $this->redirectToRoute('app_provider_profile', ['slug' => $slug]);
        }

        $provider = $this->providerProfiles->findVerifiedBySlug($slug);
        if (null === $provider) {
            throw new NotFoundHttpException('Ce professionnel est introuvable.');
        }

        $user = $this->currentUser();
        if ($provider->getUser() === $user) {
            throw $this->createAccessDeniedException();
        }

        $conversation = $this->messagingService->openConversation($user, $provider);

        return $this->redirectToRoute('app_account_messages_show', ['id' => (string) $conversation->getId()]);
    }

    /**
     * @return list<array{icon: string, title: string, subtitle: string, route: string|null, badge: string|false}>
     */
    private function menuFor(User $user): array
    {
        return \in_array('ROLE_PROVIDER', $user->getRoles(), true)
            ? StaticAccount::providerMenu()
            : StaticAccount::menu();
    }

    private function otherPartyLabel(Conversation $conversation, User $user): string
    {
        if ($conversation->getClient() === $user) {
            return $conversation->getProvider()?->getDisplayName() ?? '';
        }

        $client = $conversation->getClient();

        return null !== $client ? trim($client->getFirstName().' '.$client->getLastName()) : '';
    }

    private function hasUnreadFor(Conversation $conversation, User $user): bool
    {
        foreach ($conversation->getMessages() as $message) {
            if ($message->getAuthor() !== $user && !$message->isRead()) {
                return true;
            }
        }

        return false;
    }

    private function lastActivity(Conversation $conversation): \DateTimeImmutable
    {
        $last = $conversation->getMessages()->last() ?: null;

        return $last?->getCreatedAt() ?? $conversation->getCreatedAt() ?? new \DateTimeImmutable('@0');
    }

    private function currentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function findOrFail(string $id): Conversation
    {
        $conversation = Ulid::isValid($id) ? $this->conversations->find(Ulid::fromString($id)) : null;

        if (null === $conversation) {
            throw new NotFoundHttpException('Cette conversation est introuvable.');
        }

        return $conversation;
    }
}
