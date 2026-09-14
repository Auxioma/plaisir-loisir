<?php

declare(strict_types=1);

namespace App\Quote\Controller;

use App\Catalog\Repository\CategoryRepository;
use App\Quote\Entity\Quote;
use App\Quote\Entity\ServiceRequest;
use App\Quote\Repository\ServiceRequestRepository;
use App\Quote\Security\QuoteVoter;
use App\Quote\Security\ServiceRequestVoter;
use App\Quote\Service\QuoteService;
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
    public function index(): Response
    {
        return $this->render('quote/mes_demandes.html.twig', [
            'user' => $this->identity->identityFor($this->currentUser()),
            'menu' => StaticAccount::menu(),
            'active' => 'Mes demandes',
            'requests' => $this->requests->findByClient($this->currentUser()),
        ]);
    }

    #[Route(path: ['fr' => '/compte/demandes/nouvelle', 'en' => '/en/account/requests/new'], name: 'app_account_requests_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        // Pré-remplie depuis la fiche publique d'un professionnel : « Faire
        // une demande » y transmet son métier, pour éviter de le ressaisir.
        $preselected = (string) $request->query->get('metier', '');

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Votre session a expiré, merci de renvoyer le formulaire.');

                return $this->redirectToRoute('app_account_requests_new');
            }

            $categorySlug = (string) $request->request->get('metier', '');
            $title = trim((string) $request->request->get('titre', ''));
            $description = trim((string) $request->request->get('description', ''));

            $category = '' !== $categorySlug ? $this->categories->findOneBy(['slug' => $categorySlug]) : null;

            $errors = [];
            if (null === $category) {
                $errors[] = 'Veuillez choisir un métier.';
            }
            if ('' === $title) {
                $errors[] = 'Veuillez donner un titre à votre demande.';
            }
            if ('' === $description) {
                $errors[] = 'Veuillez décrire votre besoin.';
            }

            if ([] === $errors) {
                $serviceRequest = $this->quoteService->createRequest(
                    $this->currentUser(),
                    $category,
                    mb_substr($title, 0, 180),
                    $description,
                );

                $this->addFlash('success', 'Votre demande a bien été publiée aux professionnels du métier choisi.');

                return $this->redirectToRoute('app_account_requests_show', ['id' => (string) $serviceRequest->getId()]);
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('quote/nouvelle_demande.html.twig', [
            'user' => $this->identity->identityFor($this->currentUser()),
            'menu' => StaticAccount::menu(),
            'active' => 'Mes demandes',
            'categories' => $this->categories->findRoots(),
            'preselected' => $preselected,
        ]);
    }

    #[Route(path: ['fr' => '/compte/demandes/{id}', 'en' => '/en/account/requests/{id}'], name: 'app_account_requests_show')]
    public function show(string $id): Response
    {
        $serviceRequest = $this->findOrFail($id);

        $this->denyAccessUnlessGranted(ServiceRequestVoter::VIEW, $serviceRequest);

        return $this->render('quote/demande_detail.html.twig', [
            'user' => $this->identity->identityFor($this->currentUser()),
            'menu' => StaticAccount::menu(),
            'active' => 'Mes demandes',
            'demande' => $serviceRequest,
        ]);
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
