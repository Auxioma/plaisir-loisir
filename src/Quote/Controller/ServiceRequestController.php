<?php

declare(strict_types=1);

namespace App\Quote\Controller;

use App\Catalog\Repository\CategoryRepository;
use App\Provider\Service\ProviderDirectory;
use App\Quote\Entity\Quote;
use App\Quote\Entity\ServiceRequest;
use App\Quote\Enum\QuoteStatus;
use App\Quote\Repository\ServiceRequestRepository;
use App\Quote\Security\QuoteVoter;
use App\Quote\Security\ServiceRequestVoter;
use App\Quote\Service\QuoteService;
use App\Review\Repository\ReviewRepository;
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
 * Espace client — poser une demande, suivre les devis reçus (§10, §11 du CDC).
 *
 * ServiceRequest et Quote existaient déjà côté service (QuoteService, testé)
 * depuis l'audit du 7 septembre : il ne manquait que ces trois écrans.
 */
#[IsGranted('ROLE_USER')]
final class ServiceRequestController extends AbstractController
{
    public function __construct(
        private readonly ServiceRequestRepository $requests,
        private readonly CategoryRepository $categories,
        private readonly QuoteService $quoteService,
        private readonly AccountIdentityPresenter $identity,
        private readonly ReviewRepository $reviews,
    ) {
    }

    private function currentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    #[Route(path: ['fr' => '/compte/demandes', 'en' => '/en/account/requests'], name: 'app_account_requests')]
    public function index(Request $request): Response
    {
        $all = $this->requests->findByClient($this->currentUser());
        $tab = \in_array($request->query->get('onglet'), ['ouvertes', 'cloturees'], true) ? (string) $request->query->get('onglet') : 'toutes';
        $rows = array_values(array_filter($all, static fn (ServiceRequest $r): bool => match ($tab) {
            'ouvertes' => $r->isOpen(),
            'cloturees' => !$r->isOpen(),
            default => true,
        }));

        $quotes = 0;
        $toDecide = 0;
        foreach ($all as $r) {
            $quotes += $r->getQuotes()->count();
            if ($r->isOpen()) {
                $toDecide += \count(array_filter($r->getQuotes()->toArray(), static fn (Quote $q): bool => QuoteStatus::Pending === $q->getStatus()));
            }
        }

        return $this->render('quote/mes_demandes.html.twig', [
            'user' => $this->identity->identityFor($this->currentUser()),
            'menu' => StaticAccount::menu(),
            'active' => 'Mes demandes',
            'requests' => $rows,
            'tab' => $tab,
            'counts' => [
                'all' => \count($all),
                'open' => \count(array_filter($all, static fn (ServiceRequest $r): bool => $r->isOpen())),
                'closed' => \count(array_filter($all, static fn (ServiceRequest $r): bool => !$r->isOpen())),
                'quotes' => $quotes,
                'to_decide' => $toDecide,
            ],
        ]);
    }

    #[Route(path: ['fr' => '/compte/demandes/nouvelle', 'en' => '/en/account/requests/new'], name: 'app_account_requests_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        // Pré-remplie depuis la fiche ou l'annuaire des professionnels, qui
        // transmettent leur catégorie (paramètre « metier »).
        $values = ['category' => (string) $request->query->get('metier', ''), 'title' => '', 'description' => '', 'city' => '', 'date' => '', 'participants' => '', 'budget' => ''];
        $errors = [];

        if ($request->isMethod('POST')) {
            foreach (array_keys($values) as $key) {
                $values[$key] = trim((string) $request->request->get($key, ''));
            }
            if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
                $errors['_token'] = 'Votre session a expiré, merci de renvoyer le formulaire.';
            }

            $category = '' !== $values['category'] ? $this->categories->findOneBy(['slug' => $values['category']]) : null;
            if (null === $category) {
                $errors['category'] = 'Choisissez la catégorie de votre demande.';
            }
            if (mb_strlen($values['title']) < 5) {
                $errors['title'] = 'Donnez un titre à votre demande (5 caractères minimum).';
            }
            if (mb_strlen($values['description']) < 20) {
                $errors['description'] = 'Décrivez votre besoin (20 caractères minimum) : programme, attentes, contraintes…';
            }
            $date = null;
            if ('' !== $values['date']) {
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $values['date']) ?: null;
                if (null === $date || $date < new \DateTimeImmutable('today')) {
                    $errors['date'] = 'Choisissez une date à venir.';
                }
            }
            if ('' !== $values['participants'] && (!ctype_digit($values['participants']) || (int) $values['participants'] < 1 || (int) $values['participants'] > 1000)) {
                $errors['participants'] = 'Nombre de personnes invalide (1 à 1000).';
            }
            $budget = str_replace([',', ' ', '€'], ['.', '', ''], $values['budget']);
            if ('' !== $budget && (!is_numeric($budget) || (float) $budget < 0 || (float) $budget > 1000000)) {
                $errors['budget'] = 'Budget invalide.';
            }

            if ([] === $errors) {
                \assert(null !== $category);
                $serviceRequest = $this->quoteService->createRequest(
                    $this->currentUser(),
                    $category,
                    mb_substr($values['title'], 0, 180),
                    $values['description'],
                    [
                        'city' => '' !== $values['city'] ? mb_substr($values['city'], 0, 120) : null,
                        'date' => $date,
                        'participants' => '' !== $values['participants'] ? (int) $values['participants'] : null,
                        'budget' => '' !== $budget ? number_format((float) $budget, 2, '.', '') : null,
                    ],
                );

                $this->addFlash('success', 'Votre demande est publiée : les professionnels concernés sont prévenus et vous enverront leurs devis.');

                return $this->redirectToRoute('app_account_requests_show', ['id' => (string) $serviceRequest->getId()]);
            }
        }

        return $this->render('quote/nouvelle_demande.html.twig', [
            'user' => $this->identity->identityFor($this->currentUser()),
            'menu' => StaticAccount::menu(),
            'active' => 'Mes demandes',
            'categories' => $this->categories->findRoots(),
            'values' => $values,
            'errors' => $errors,
        ], new Response(null, [] === $errors ? 200 : 422));
    }

    #[Route(path: ['fr' => '/compte/demandes/{id}', 'en' => '/en/account/requests/{id}'], name: 'app_account_requests_show')]
    public function show(string $id, ProviderDirectory $directory): Response
    {
        $serviceRequest = $this->findOrFail($id);

        $this->denyAccessUnlessGranted(ServiceRequestVoter::VIEW, $serviceRequest);

        $reviewedQuoteIds = [];
        $providers = [];
        foreach ($serviceRequest->getQuotes() as $quote) {
            if (null !== $this->reviews->findOneByQuote($quote)) {
                $reviewedQuoteIds[] = (string) $quote->getId();
            }
            if (null !== ($provider = $quote->getProvider())) {
                $providers[(string) $quote->getId()] = $directory->card($provider);
            }
        }

        // Devis du moins cher au plus cher, l'accepté en tête.
        $quotes = $serviceRequest->getQuotes()->toArray();
        usort($quotes, static fn (Quote $a, Quote $b): int => [QuoteStatus::Accepted !== $a->getStatus(), (float) $a->getAmount()] <=> [QuoteStatus::Accepted !== $b->getStatus(), (float) $b->getAmount()]);

        return $this->render('quote/demande_detail.html.twig', [
            'user' => $this->identity->identityFor($this->currentUser()),
            'menu' => StaticAccount::menu(),
            'active' => 'Mes demandes',
            'demande' => $serviceRequest,
            'quotes' => $quotes,
            'providers' => $providers,
            'reviewed_quote_ids' => $reviewedQuoteIds,
        ]);
    }

    #[Route(path: ['fr' => '/compte/demandes/{id}/cloturer', 'en' => '/en/account/requests/{id}/close'], name: 'app_account_requests_close', methods: ['POST'])]
    public function close(string $id, Request $request): Response
    {
        $serviceRequest = $this->findOrFail($id);
        $this->denyAccessUnlessGranted(ServiceRequestVoter::MANAGE, $serviceRequest);

        if ($this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->quoteService->close($serviceRequest);
            $this->addFlash('success', 'Demande clôturée : les professionnels qui vous ont répondu sont prévenus.');
        }

        return $this->redirectToRoute('app_account_requests_show', ['id' => $id]);
    }

    #[Route(path: ['fr' => '/compte/demandes/{id}/devis/{quoteId}/accepter', 'en' => '/en/account/requests/{id}/quotes/{quoteId}/accept'], name: 'app_account_requests_quote_accept', methods: ['POST'])]
    public function acceptQuote(string $id, string $quoteId, Request $request): Response
    {
        $serviceRequest = $this->findOrFail($id);
        $this->denyAccessUnlessGranted(ServiceRequestVoter::MANAGE, $serviceRequest);

        $quote = $this->findQuoteOrFail($serviceRequest, $quoteId);
        $this->denyAccessUnlessGranted(QuoteVoter::DECIDE, $quote);

        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return $this->redirectToRoute('app_account_requests_show', ['id' => $id]);
        }

        $this->quoteService->accept($quote);
        $this->addFlash('success', 'Devis accepté : les autres propositions ont été automatiquement refusées.');

        return $this->redirectToRoute('app_account_requests_show', ['id' => $id]);
    }

    #[Route(path: ['fr' => '/compte/demandes/{id}/devis/{quoteId}/refuser', 'en' => '/en/account/requests/{id}/quotes/{quoteId}/decline'], name: 'app_account_requests_quote_decline', methods: ['POST'])]
    public function declineQuote(string $id, string $quoteId, Request $request): Response
    {
        $serviceRequest = $this->findOrFail($id);
        $this->denyAccessUnlessGranted(ServiceRequestVoter::MANAGE, $serviceRequest);

        $quote = $this->findQuoteOrFail($serviceRequest, $quoteId);
        $this->denyAccessUnlessGranted(QuoteVoter::DECIDE, $quote);

        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return $this->redirectToRoute('app_account_requests_show', ['id' => $id]);
        }

        $this->quoteService->decline($quote);
        $this->addFlash('success', 'Devis refusé.');

        return $this->redirectToRoute('app_account_requests_show', ['id' => $id]);
    }

    private function findOrFail(string $id): ServiceRequest
    {
        $serviceRequest = Ulid::isValid($id) ? $this->requests->find(Ulid::fromString($id)) : null;

        if (null === $serviceRequest) {
            throw new NotFoundHttpException('Cette demande est introuvable.');
        }

        return $serviceRequest;
    }

    private function findQuoteOrFail(ServiceRequest $serviceRequest, string $quoteId): Quote
    {
        foreach ($serviceRequest->getQuotes() as $quote) {
            if ((string) $quote->getId() === $quoteId) {
                return $quote;
            }
        }

        throw new NotFoundHttpException('Ce devis est introuvable.');
    }
}
