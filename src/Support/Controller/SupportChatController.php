<?php

declare(strict_types=1);

namespace App\Support\Controller;

use App\Notification\Enum\NotificationCategory;
use App\Notification\Service\NotificationService;
use App\Shared\Service\AccountIdentityPresenter;
use App\Support\Entity\SupportTicket;
use App\Support\Entity\SupportTicketMessage;
use App\Support\Enum\TicketCategory;
use App\Support\Enum\TicketStatus;
use App\Support\Repository\SupportTicketRepository;
use App\User\Entity\User;
use App\User\Repository\UserRepository;
use App\User\StaticAccount;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Chat en ligne » de la page Contactez-nous (04/10) : ouvre une
 * conversation avec l'équipe (SupportTicket « Chat en direct »), suivie dans
 * l'espace du membre ; l'équipe répond depuis le back-office.
 */
final class SupportChatController extends AbstractController
{
    public function __construct(
        private readonly SupportTicketRepository $tickets,
        private readonly UserRepository $users,
        private readonly NotificationService $notifications,
        private readonly EntityManagerInterface $entityManager,
        private readonly AccountIdentityPresenter $identity,
    ) {
    }

    #[Route(path: ['fr' => '/aide/chat', 'en' => '/en/help/chat'], name: 'app_support_chat', methods: ['GET', 'POST'])]
    public function start(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('info', 'Connectez-vous pour discuter avec notre équipe.');

            return $this->redirectToRoute('app_login');
        }
        if ($request->isMethod('POST') && !$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_corporate_contact');
        }

        $ticket = (new SupportTicket())
            ->setNumber($this->tickets->nextNumber())
            ->setAuthor($user)
            ->setSubject('Conversation avec le support')
            ->setCategory(TicketCategory::Chat);
        $ticket->addMessage((new SupportTicketMessage())->setAuthor($user)->setBody(trim((string) $request->request->get('message', '')) ?: 'Bonjour, j’aimerais échanger avec un conseiller.'));
        $this->entityManager->persist($ticket);
        $this->entityManager->flush();
        foreach ($this->users->findAdmins() as $admin) {
            $this->notifications->notify($admin, NotificationCategory::System, 'Support : '.$ticket->getReference(), 'Chat en direct — nouvelle conversation');
        }
        $this->addFlash('success', 'Conversation ouverte : un conseiller vous répond ici, en général en moins de 2 h.');

        return \in_array('ROLE_PROVIDER', $user->getRoles(), true)
            ? $this->redirectToRoute('app_pro_support_show', ['number' => $ticket->getNumber()])
            : $this->redirectToRoute('app_account_support_show', ['number' => $ticket->getNumber()]);
    }

    #[Route(path: ['fr' => '/compte/support', 'en' => '/en/account/support'], name: 'app_account_support')]
    public function index(): Response
    {
        $user = $this->currentUser();

        return $this->render('account/support.html.twig', [
            'user' => $this->identity->identityFor($user),
            'menu' => StaticAccount::menu(),
            'active' => '',
            'tickets' => $this->tickets->findForAuthor($user),
        ]);
    }

    #[Route(path: ['fr' => '/compte/support/{number}', 'en' => '/en/account/support/{number}'], name: 'app_account_support_show', requirements: ['number' => '\d+'], methods: ['GET', 'POST'])]
    public function show(int $number, Request $request): Response
    {
        $user = $this->currentUser();
        $ticket = $this->tickets->findOneBy(['number' => $number]);
        if (null === $ticket || $ticket->getAuthor() !== $user) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST') && $this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            if ('close' === $request->request->get('action')) {
                $ticket->setStatus(TicketStatus::Closed);
            } elseif ('' !== ($body = trim((string) $request->request->get('message', '')))) {
                $ticket->addMessage((new SupportTicketMessage())->setAuthor($user)->setBody(mb_substr($body, 0, 5000)));
                if (\in_array($ticket->getStatus(), [TicketStatus::Resolved, TicketStatus::Closed], true)) {
                    $ticket->setStatus(TicketStatus::Open);
                }
            }
            $this->entityManager->flush();

            return $this->redirectToRoute('app_account_support_show', ['number' => $number, '_fragment' => 'fin']);
        }

        return $this->render('account/support_ticket.html.twig', [
            'user' => $this->identity->identityFor($user),
            'menu' => StaticAccount::menu(),
            'active' => '',
            'ticket' => $ticket,
        ]);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
