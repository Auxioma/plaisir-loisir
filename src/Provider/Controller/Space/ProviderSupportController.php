<?php

declare(strict_types=1);

namespace App\Provider\Controller\Space;

use App\Legal\InitialLegalTexts;
use App\Notification\Enum\NotificationCategory;
use App\Notification\Service\NotificationService;
use App\Support\Entity\SupportTicket;
use App\Support\Entity\SupportTicketMessage;
use App\Support\Enum\FaqCategory;
use App\Support\Enum\TicketCategory;
use App\Support\Enum\TicketStatus;
use App\Support\Repository\FaqEntryRepository;
use App\Support\Repository\SupportTicketRepository;
use App\User\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * « Support » — maquette profil_support_professionnel.jpeg (02/10) :
 * ticket écrit, demande de rappel et chat (un ticket « Chat en direct »
 * ouvert et suivi en fil), liste « Mes tickets », FAQ des professionnels.
 * L'équipe répond depuis le back-office (SupportTicketCrudController).
 */
#[IsGranted('ROLE_PROVIDER')]
final class ProviderSupportController extends AbstractProviderSpaceController
{
    private const PER_PAGE = 5;

    public function __construct(
        private readonly SupportTicketRepository $tickets,
        private readonly FaqEntryRepository $faq,
        private readonly UserRepository $users,
        private readonly NotificationService $notifications,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(path: ['fr' => '/pro/support', 'en' => '/en/pro/support'], name: 'app_pro_support')]
    public function index(Request $request): Response
    {
        $all = $this->tickets->findForAuthor($this->currentUser());
        $query = trim((string) $request->query->get('q', ''));
        $status = (string) $request->query->get('statut', '');
        $showAll = $request->query->getBoolean('tous');

        $rows = array_values(array_filter($all, static fn (SupportTicket $t): bool => ('' === $status || $t->getStatus()->value === $status)
            && ('' === $query || false !== mb_stripos($t->getReference().' '.$t->getSubject().' '.$t->getCategory()->label(), $query))));
        $page = max(1, $request->query->getInt('page', 1));
        $pages = max(1, (int) ceil(\count($rows) / self::PER_PAGE));

        $faq = array_values(array_filter($this->faq->featured($request->getLocale(), 12), static fn ($e): bool => \in_array($e->getCategory(), [FaqCategory::Providers, FaqCategory::Payment, FaqCategory::Booking], true)));

        return $this->renderSpace('provider/space/support.html.twig', 'Support', [
            'rows' => $showAll ? \array_slice($rows, (min($page, $pages) - 1) * self::PER_PAGE, self::PER_PAGE) : \array_slice($rows, 0, self::PER_PAGE),
            'total' => \count($rows),
            'page' => min($page, $pages),
            'pages' => $pages,
            'show_all' => $showAll,
            'query' => $query,
            'status' => $status,
            'statuses' => TicketStatus::cases(),
            'categories' => array_filter(TicketCategory::cases(), static fn (TicketCategory $c): bool => !\in_array($c, [TicketCategory::Callback, TicketCategory::Chat], true)),
            'subject' => (string) $request->query->get('sujet', ''),
            'faq' => \array_slice($faq, 0, 5),
            'contact' => ['email' => InitialLegalTexts::CONTACT, 'phone' => InitialLegalTexts::TELEPHONE],
            'promo' => ['title' => 'Besoin d’aide ?', 'text' => 'Notre équipe est là pour vous accompagner à chaque étape.', 'cta' => 'Nous contacter', 'href' => $this->generateUrl('app_pro_support').'#ticket'],
        ]);
    }

    /** Ticket écrit, demande de rappel (`category=callback`) ou chat (`category=chat`). */
    #[Route(path: ['fr' => '/pro/support/tickets', 'en' => '/en/pro/support/tickets'], name: 'app_pro_support_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        if (!$this->csrfOk($request)) {
            return $this->redirectToRoute('app_pro_support');
        }

        $category = TicketCategory::tryFrom((string) $request->request->get('category')) ?? TicketCategory::Other;
        $subject = trim((string) $request->request->get('subject', ''));
        $body = trim((string) $request->request->get('message', ''));
        $phone = self::nullIfEmpty($request->request->get('phone'), 30);

        if (TicketCategory::Chat === $category) {
            $subject = '' !== $subject ? $subject : 'Conversation avec le support';
            $body = '' !== $body ? $body : 'Bonjour, j’aimerais échanger avec un conseiller.';
        }
        if (TicketCategory::Callback === $category) {
            if (null === $phone) {
                $this->addFlash('error', 'Indiquez le numéro auquel vous rappeler.');

                return $this->redirectToRoute('app_pro_support');
            }
            $subject = '' !== $subject ? $subject : 'Demande de rappel';
            $body = sprintf("Merci de me rappeler au %s%s.\n%s", $phone, '' !== (string) $request->request->get('slot', '') ? ' ('.$request->request->get('slot').')' : '', $body);
        }
        if ('' === $subject || '' === $body) {
            $this->addFlash('error', 'Indiquez un sujet et décrivez votre demande.');

            return $this->redirectToRoute('app_pro_support', ['_fragment' => 'ticket']);
        }

        $ticket = (new SupportTicket())
            ->setNumber($this->tickets->nextNumber())
            ->setAuthor($this->currentUser())
            ->setSubject(mb_substr($subject, 0, 180))
            ->setCategory($category)
            ->setPhone($phone);
        $ticket->addMessage((new SupportTicketMessage())->setAuthor($this->currentUser())->setBody(mb_substr($body, 0, 5000)));
        $this->entityManager->persist($ticket);
        $this->entityManager->flush();

        $this->notifyStaff($ticket);
        $this->addFlash('success', match ($category) {
            TicketCategory::Callback => 'Demande de rappel enregistrée : un conseiller vous appelle dans les meilleurs délais (lun.–ven., 9h–18h).',
            TicketCategory::Chat => 'Conversation ouverte : un conseiller vous répond ici, en général en moins de 2 h.',
            default => sprintf('Ticket %s créé : nous vous répondons en moins de 2 h ouvrées.', $ticket->getReference()),
        });

        return $this->redirectToRoute('app_pro_support_show', ['number' => $ticket->getNumber()]);
    }

    #[Route(path: ['fr' => '/pro/support/tickets/{number}', 'en' => '/en/pro/support/tickets/{number}'], name: 'app_pro_support_show', requirements: ['number' => '\d+'], methods: ['GET', 'POST'])]
    public function show(int $number, Request $request): Response
    {
        $ticket = $this->own($number);

        if ($request->isMethod('POST') && $this->csrfOk($request)) {
            $action = (string) $request->request->get('action', 'reply');
            if ('close' === $action) {
                $ticket->setStatus(TicketStatus::Closed);
                $this->addFlash('success', 'Ticket fermé.');
            } elseif ('reopen' === $action) {
                $ticket->setStatus(TicketStatus::Open);
                $this->addFlash('success', 'Ticket rouvert.');
            } else {
                $body = trim((string) $request->request->get('message', ''));
                if ('' !== $body) {
                    $ticket->addMessage((new SupportTicketMessage())->setAuthor($this->currentUser())->setBody(mb_substr($body, 0, 5000)));
                    if (\in_array($ticket->getStatus(), [TicketStatus::Resolved, TicketStatus::Closed], true)) {
                        $ticket->setStatus(TicketStatus::Open);
                    }
                    $this->notifyStaff($ticket);
                }
            }
            $this->entityManager->flush();

            return $this->redirectToRoute('app_pro_support_show', ['number' => $number, '_fragment' => 'fin']);
        }

        return $this->renderSpace('provider/space/support_ticket.html.twig', 'Support', [
            'ticket' => $ticket,
            'me' => $this->currentUser(),
        ]);
    }

    private function own(int $number): SupportTicket
    {
        $ticket = $this->tickets->findOneBy(['number' => $number]);
        if (null === $ticket || $ticket->getAuthor() !== $this->currentUser()) {
            throw $this->createNotFoundException('Ticket introuvable.');
        }

        return $ticket;
    }

    /** Prévient les administrateurs (cloche du back-office). */
    private function notifyStaff(SupportTicket $ticket): void
    {
        foreach ($this->users->findAdmins() as $admin) {
            $this->notifications->notify($admin, NotificationCategory::System, 'Support : '.$ticket->getReference(), sprintf('%s — %s', $ticket->getCategory()->label(), $ticket->getSubject()));
        }
    }
}
