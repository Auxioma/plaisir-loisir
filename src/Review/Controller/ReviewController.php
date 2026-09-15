<?php

declare(strict_types=1);

namespace App\Review\Controller;

use App\Provider\Entity\ProviderProfile;
use App\Provider\Repository\ProviderProfileRepository;
use App\Quote\Entity\Quote;
use App\Quote\Repository\QuoteRepository;
use App\Review\Entity\Review;
use App\Review\Repository\ReviewRepository;
use App\Review\Security\ReviewVoter;
use App\Review\Service\ReviewModerationService;
use App\Review\Service\ReviewService;
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
 * Avis et notation des professionnels (§16.2 du CDC) : dépôt côté client,
 * une fois un devis accepté, et réponse côté professionnel.
 */
#[IsGranted('ROLE_USER')]
final class ReviewController extends AbstractController
{
    public function __construct(
        private readonly QuoteRepository $quotes,
        private readonly ReviewRepository $reviews,
        private readonly ReviewService $reviewService,
        private readonly ReviewModerationService $moderation,
        private readonly ProviderProfileRepository $providerProfiles,
        private readonly AccountIdentityPresenter $identity,
    ) {
    }

    #[Route(path: ['fr' => '/compte/devis/{id}/avis', 'en' => '/en/account/quotes/{id}/review'], name: 'app_account_quote_review', methods: ['POST'])]
    public function create(string $id, Request $request): Response
    {
        $quote = $this->findQuoteOrFail($id);
        $this->denyAccessUnlessGranted(ReviewVoter::CREATE, $quote);

        $requestId = (string) $quote->getServiceRequest()?->getId();

        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return $this->redirectToRoute('app_account_requests_show', ['id' => $requestId]);
        }

        $rating = (int) $request->request->get('note', 0);
        $comment = trim((string) $request->request->get('commentaire', ''));

        try {
            $this->reviewService->addReview($quote, $this->currentUser(), $rating, '' !== $comment ? $comment : null);
            $this->addFlash('success', 'Votre avis a bien été publié, merci.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_account_requests_show', ['id' => $requestId]);
    }

    #[Route(path: ['fr' => '/pro/avis', 'en' => '/en/pro/reviews'], name: 'app_pro_reviews')]
    #[IsGranted('ROLE_PROVIDER')]
    public function index(): Response
    {
        $provider = $this->currentProvider();

        return $this->render('review/avis_recus.html.twig', [
            'user' => $this->identity->identityFor($this->currentUser()),
            'menu' => StaticAccount::providerMenu(),
            'active' => 'Avis reçus',
            'reviews' => $this->reviews->findForProvider($provider),
            'average' => $this->reviews->averageRatingForProvider($provider),
        ]);
    }

    #[Route(path: ['fr' => '/pro/avis/{id}/repondre', 'en' => '/en/pro/reviews/{id}/reply'], name: 'app_pro_reviews_reply', methods: ['POST'])]
    #[IsGranted('ROLE_PROVIDER')]
    public function reply(string $id, Request $request): Response
    {
        $review = $this->findReviewOrFail($id);
        $this->denyAccessUnlessGranted(ReviewVoter::REPLY, $review);

        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return $this->redirectToRoute('app_pro_reviews');
        }

        $text = trim((string) $request->request->get('reponse', ''));
        if ('' !== $text) {
            $this->moderation->reply($review, $this->currentUser(), $text);
            $this->addFlash('success', 'Votre réponse a été publiée.');
        }

        return $this->redirectToRoute('app_pro_reviews');
    }

    private function currentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function currentProvider(): ProviderProfile
    {
        $provider = $this->providerProfiles->findOneByUser($this->currentUser());

        if (null === $provider) {
            throw $this->createAccessDeniedException('Aucun dossier prestataire rattaché à ce compte.');
        }

        return $provider;
    }

    private function findQuoteOrFail(string $id): Quote
    {
        $quote = Ulid::isValid($id) ? $this->quotes->find(Ulid::fromString($id)) : null;

        if (null === $quote) {
            throw new NotFoundHttpException('Ce devis est introuvable.');
        }

        return $quote;
    }

    private function findReviewOrFail(string $id): Review
    {
        $review = Ulid::isValid($id) ? $this->reviews->find(Ulid::fromString($id)) : null;

        if (null === $review) {
            throw new NotFoundHttpException('Cet avis est introuvable.');
        }

        return $review;
    }
}
