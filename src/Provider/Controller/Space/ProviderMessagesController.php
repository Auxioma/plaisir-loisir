<?php

declare(strict_types=1);

namespace App\Provider\Controller\Space;

use App\Booking\Entity\Booking;
use App\Messaging\Entity\Conversation;
use App\Messaging\Entity\Message;
use App\Messaging\Repository\ConversationRepository;
use App\Messaging\Service\MessagingService;
use App\Provider\Entity\ClientNote;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Repository\ClientNoteRepository;
use App\Provider\Service\ProviderSpace;
use App\Review\Entity\Review;
use App\User\Entity\User;
use App\User\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

/**
 * « Clients & Messages » — maquette profil_clients&messages_professionnel.jpeg
 * (02/10) : liste des conversations (toutes, non lues, liées à une
 * réservation, archives), fil de messages, fiche client (coordonnées,
 * dernière réservation, notes privées, statistiques).
 *
 * Même modèle que la messagerie client (Conversation/Message,
 * MessagingService) : un message envoyé ici arrive dans /compte/messages.
 */
#[IsGranted('ROLE_PROVIDER')]
final class ProviderMessagesController extends AbstractProviderSpaceController
{
    private const LIST_STEP = 8;

    public function __construct(
        private readonly ProviderSpace $space,
        private readonly ConversationRepository $conversations,
        private readonly MessagingService $messaging,
        private readonly ClientNoteRepository $notes,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(path: ['fr' => '/pro/messages', 'en' => '/en/pro/messages'], name: 'app_pro_messages')]
    public function index(Request $request): Response
    {
        $provider = $this->currentProvider();
        $me = $this->currentUser();

        // « Contacter le client » depuis une réservation : ouvre (ou crée) le fil.
        $clientId = (string) $request->query->get('client', '');
        if ('' !== $clientId && Ulid::isValid($clientId)) {
            $client = $this->users->find(Ulid::fromString($clientId));
            if ($client instanceof User && $this->isClientOf($client, $provider)) {
                $conversation = $this->messaging->openConversation($client, $provider);

                return $this->redirectToRoute('app_pro_messages', ['conversation' => (string) $conversation->getId()]);
            }
        }

        $bookings = $this->space->bookings($provider);
        $all = array_values(array_filter($this->conversations->findForUser($me), static fn (Conversation $c): bool => $c->getProvider() === $provider));
        usort($all, fn (Conversation $a, Conversation $b): int => $this->lastAt($b) <=> $this->lastAt($a));

        $bookedClients = [];
        foreach ($bookings as $b) {
            $bookedClients[(string) $b->getClient()?->getId()] = true;
        }

        $tab = (string) $request->query->get('onglet', 'toutes');
        $query = trim((string) $request->query->get('q', ''));
        $counts = [
            'toutes' => \count(array_filter($all, static fn (Conversation $c): bool => !$c->isArchivedByProvider())),
            'non-lues' => \count(array_filter($all, fn (Conversation $c): bool => !$c->isArchivedByProvider() && $this->unread($c, $me) > 0)),
            'reservations' => \count(array_filter($all, static fn (Conversation $c): bool => !$c->isArchivedByProvider() && isset($bookedClients[(string) $c->getClient()?->getId()]))),
            'archives' => \count(array_filter($all, static fn (Conversation $c): bool => $c->isArchivedByProvider())),
        ];

        $list = array_values(array_filter($all, function (Conversation $c) use ($tab, $query, $me, $bookedClients): bool {
            $ok = match ($tab) {
                'non-lues' => !$c->isArchivedByProvider() && $this->unread($c, $me) > 0,
                'reservations' => !$c->isArchivedByProvider() && isset($bookedClients[(string) $c->getClient()?->getId()]),
                'archives' => $c->isArchivedByProvider(),
                default => !$c->isArchivedByProvider(),
            };
            if (!$ok || '' === $query) {
                return $ok;
            }
            $text = $c->getClient()?->getFirstName().' '.$c->getClient()?->getLastName();
            foreach ($c->getMessages() as $m) {
                $text .= ' '.$m->getBody();
            }

            return false !== mb_stripos($text, $query);
        }));

        $shown = max(self::LIST_STEP, $request->query->getInt('n', self::LIST_STEP));

        $selectedId = (string) $request->query->get('conversation', '');
        $selected = null;
        foreach ($all as $c) {
            if ((string) $c->getId() === $selectedId) {
                $selected = $c;
            }
        }
        $selected ??= $list[0] ?? null;

        $client = $selected?->getClient();
        $details = null;
        if (null !== $selected && null !== $client) {
            // Ouvrir un fil le marque lu ; pas quand on arrive juste sur
            // l'onglet « Non lues » (sinon « Marquer non lu » s'annulerait).
            if ('' !== $selectedId || 'non-lues' !== $tab) {
                $this->messaging->markRead($selected, $me);
            }
            $own = array_values(array_filter($bookings, static fn (Booking $b): bool => $b->getClient() === $client));
            $reviews = array_filter($this->space->reviews($provider), static fn (Review $r): bool => $r->getAuthor() === $client);
            $details = [
                'bookings' => $own,
                'last' => $own[0] ?? null,
                'spent' => array_sum(array_map(static fn (Booking $b): float => (float) $b->getTotalPrice(), array_filter($own, static fn (Booking $b): bool => \in_array($b->getStatus(), ProviderSpace::ACTIVE_STATUSES, true)))),
                'reviews' => \count($reviews),
                'note' => $this->notes->findOneBy(['provider' => $provider, 'client' => $client]),
                'city' => $client->getMainAddress()?->getCity(),
            ];
        }

        $rows = [];
        foreach (\array_slice($list, 0, $shown) as $c) {
            $last = $c->getMessages()->last() ?: null;
            $rows[] = ['c' => $c, 'last' => $last, 'unread' => $this->unread($c, $me), 'at' => $this->lastAt($c)];
        }

        return $this->renderSpace('provider/space/messages.html.twig', 'Clients & Messages', [
            'rows' => $rows,
            'has_more' => \count($list) > $shown,
            'shown' => $shown,
            'step' => self::LIST_STEP,
            'counts' => $counts,
            'tab' => $tab,
            'query' => $query,
            'selected' => $selected,
            'client' => $client,
            'details' => $details,
            'me' => $me,
        ]);
    }

    #[Route(path: ['fr' => '/pro/messages/{id}/envoyer', 'en' => '/en/pro/messages/{id}/send'], name: 'app_pro_messages_send', methods: ['POST'])]
    public function send(string $id, Request $request): Response
    {
        $conversation = $this->own($id);
        if (!$this->csrfOk($request)) {
            return $this->redirectToRoute('app_pro_messages', ['conversation' => $id]);
        }

        $body = trim((string) $request->request->get('body', ''));
        if ('' === $body) {
            $this->addFlash('error', 'Votre message est vide.');
        } else {
            $this->messaging->sendMessage($conversation, $this->currentUser(), mb_substr($body, 0, 5000));
            if ($conversation->isArchivedByProvider()) {
                $conversation->setArchivedByProvider(false);
                $this->entityManager->flush();
            }
        }

        return $this->redirectToRoute('app_pro_messages', ['conversation' => $id, '_fragment' => 'fin']);
    }

    #[Route(path: ['fr' => '/pro/messages/{id}/archiver', 'en' => '/en/pro/messages/{id}/archive'], name: 'app_pro_messages_archive', methods: ['POST'])]
    public function archive(string $id, Request $request): Response
    {
        $conversation = $this->own($id);
        if ($this->csrfOk($request)) {
            $conversation->setArchivedByProvider(!$conversation->isArchivedByProvider());
            $this->entityManager->flush();
            $this->addFlash('success', $conversation->isArchivedByProvider() ? 'Conversation archivée.' : 'Conversation désarchivée.');
        }

        return $this->redirectToRoute('app_pro_messages', ['onglet' => $conversation->isArchivedByProvider() ? 'archives' : 'toutes']);
    }

    #[Route(path: ['fr' => '/pro/messages/{id}/non-lu', 'en' => '/en/pro/messages/{id}/unread'], name: 'app_pro_messages_unread', methods: ['POST'])]
    public function markUnread(string $id, Request $request): Response
    {
        $conversation = $this->own($id);
        if ($this->csrfOk($request)) {
            $last = null;
            foreach ($conversation->getMessages() as $m) {
                if ($m->getAuthor() !== $this->currentUser()) {
                    $last = $m;
                }
            }
            if ($last instanceof Message) {
                $last->markAsUnread();
                $this->entityManager->flush();
            }
        }

        return $this->redirectToRoute('app_pro_messages', ['onglet' => 'non-lues']);
    }

    #[Route(path: ['fr' => '/pro/messages/{id}/note', 'en' => '/en/pro/messages/{id}/note'], name: 'app_pro_messages_note', methods: ['POST'])]
    public function note(string $id, Request $request): Response
    {
        $conversation = $this->own($id);
        $client = $conversation->getClient();
        if ($this->csrfOk($request) && null !== $client) {
            $provider = $this->currentProvider();
            $note = $this->notes->findOneBy(['provider' => $provider, 'client' => $client]);
            $body = trim((string) $request->request->get('body', ''));
            if ('' === $body) {
                if (null !== $note) {
                    $this->entityManager->remove($note);
                }
            } else {
                if (null === $note) {
                    $note = new ClientNote($provider, $client);
                    $this->entityManager->persist($note);
                }
                $note->setBody(mb_substr($body, 0, 2000));
            }
            $this->entityManager->flush();
            $this->addFlash('success', 'Note privée enregistrée.');
        }

        return $this->redirectToRoute('app_pro_messages', ['conversation' => $id]);
    }

    private function own(string $id): Conversation
    {
        $conversation = Ulid::isValid($id) ? $this->conversations->find(Ulid::fromString($id)) : null;
        if (null === $conversation || $conversation->getProvider() !== $this->currentProvider()) {
            throw $this->createNotFoundException('Conversation introuvable.');
        }

        return $conversation;
    }

    private function unread(Conversation $c, User $me): int
    {
        $n = 0;
        foreach ($c->getMessages() as $m) {
            if ($m->getAuthor() !== $me && null === $m->getReadAt()) {
                ++$n;
            }
        }

        return $n;
    }

    private function lastAt(Conversation $c): \DateTimeImmutable
    {
        $last = $c->getMessages()->last();

        return ($last ? $last->getCreatedAt() : null) ?? $c->getCreatedAt() ?? new \DateTimeImmutable('@0');
    }

    private function isClientOf(User $client, ProviderProfile $provider): bool
    {
        foreach ($this->space->bookings($provider) as $b) {
            if ($b->getClient() === $client) {
                return true;
            }
        }

        return null !== $this->conversations->findOneByClientAndProvider($client, $provider);
    }
}
