<?php

declare(strict_types=1);

namespace App\Review\Controller;

use App\Booking\Repository\BookingRepository;
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

    /**
     * Avis d'un client sur une activité réservée et terminée (02/10) : il
     * alimente « Avis & Évaluations » côté professionnel.
     */
    #[Route(path: ['fr' => '/compte/reservations/{id}/avis', 'en' => '/en/account/bookings/{id}/review'], name: 'app_account_booking_review', methods: ['GET', 'POST'])]
    public function bookingReview(string $id, Request $request, BookingRepository $bookings): Response
    {
        $booking = Ulid::isValid($id) ? $bookings->find(Ulid::fromString($id)) : null;
        if (null === $booking || $booking->getClient() !== $this->currentUser()) {
            throw new NotFoundHttpException('Cette réservation est introuvable.');
        }

        $existing = $this->reviews->findOneBy(['booking' => $booking]);

        if ($request->isMethod('POST') && null === $existing) {
            if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

                return $this->redirectToRoute('app_account_booking_review', ['id' => $id]);
            }

            try {
                $comment = trim((string) $request->request->get('commentaire', ''));
                $this->reviewService->addBookingReview($booking, $this->currentUser(), (int) $request->request->get('note', 0), '' !== $comment ? $comment : null);
                $this->addFlash('success', 'Votre avis a bien été publié, merci.');

                return $this->redirectToRoute('app_account_history');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('review/booking_review.html.twig', [
            'user' => $this->identity->identityFor($this->currentUser()),
            'menu' => StaticAccount::menu(),
            'active' => 'Mes réservations',
            'booking' => $booking,
            'existing' => $existing,
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

        $back = (string) $request->headers->get('referer', '');
        $text = trim((string) $request->request->get('reponse', ''));
        if ('' !== $text) {
            $this->moderation->reply($review, $this->currentUser(), $text);
            $this->addFlash('success', 'Votre réponse a été publiée.');
        }

        return str_starts_with($back, $request->getSchemeAndHttpHost().'/') ? $this->redirect($back) : $this->redirectToRoute('app_pro_reviews');
    }

    private function currentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
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
