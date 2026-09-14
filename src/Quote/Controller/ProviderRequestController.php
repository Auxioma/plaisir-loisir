<?php

declare(strict_types=1);

namespace App\Quote\Controller;

use App\Provider\Entity\ProviderProfile;
use App\Provider\Repository\ProviderProfileRepository;
use App\Quote\Entity\ServiceRequest;
use App\Quote\Repository\QuoteRepository;
use App\Quote\Repository\ServiceRequestRepository;
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
 * Espace professionnel — demandes reçues et dépôt de devis (§10, §11 du CDC).
 *
 * Double verrou volontaire, comme partout ailleurs dans Provider (voir
 * CLAUDE.md) : ROLE_PROVIDER donne accès à l'écran, mais seul un dossier
 * VÉRIFIÉ peut réellement déposer un devis — ServiceRequestVoter::SUBMIT_QUOTE
 * fait respecter la seconde condition.
 */
#[IsGranted('ROLE_PROVIDER')]
final class ProviderRequestController extends AbstractController
{
    public function __construct(
        private readonly ServiceRequestRepository $requests,
        private readonly QuoteRepository $quotes,
        private readonly ProviderProfileRepository $providerProfiles,
        private readonly QuoteService $quoteService,
        private readonly AccountIdentityPresenter $identity,
    ) {
    }

    #[Route(path: ['fr' => '/pro/demandes', 'en' => '/en/pro/requests'], name: 'app_pro_requests')]
    public function index(): Response
    {
        $profile = $this->currentProfile();
        $category = $profile->getMainCategory();

        $open = null !== $category ? $this->requests->findOpenForCategory($category) : [];

        return $this->render('quote/demandes_recues.html.twig', [
            'user' => $this->identity->identityFor($this->currentUser()),
            'menu' => StaticAccount::providerMenu(),
            'active' => 'Demandes reçues',
            'provider' => $profile,
            'requests' => $open,
            'quoted_ids' => $this->alreadyQuotedIds($profile, $open),
        ]);
    }

    #[Route(path: ['fr' => '/pro/demandes/{id}', 'en' => '/en/pro/requests/{id}'], name: 'app_pro_requests_show', methods: ['GET', 'POST'])]
    public function show(string $id, Request $request): Response
    {
        $serviceRequest = $this->findOrFail($id);
        $this->denyAccessUnlessGranted(ServiceRequestVoter::VIEW, $serviceRequest);

        $profile = $this->currentProfile();
        $existingQuote = $this->quotes->findOneByRequestAndProvider($serviceRequest, $profile);

        if ($request->isMethod('POST')) {
            $this->denyAccessUnlessGranted(ServiceRequestVoter::SUBMIT_QUOTE, $serviceRequest);

            if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

                return $this->redirectToRoute('app_pro_requests_show', ['id' => $id]);
            }

            $amount = trim((string) $request->request->get('montant', ''));
            $message = trim((string) $request->request->get('message', ''));

            try {
                $this->quoteService->submitQuote(
                    $serviceRequest,
                    $profile,
                    $this->normalizeAmount($amount),
                    '' !== $message ? $message : null,
                );

                $this->addFlash('success', 'Votre devis a bien été envoyé au client.');

                return $this->redirectToRoute('app_pro_requests_show', ['id' => $id]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('quote/demande_recue_detail.html.twig', [
            'user' => $this->identity->identityFor($this->currentUser()),
            'menu' => StaticAccount::providerMenu(),
            'active' => 'Demandes reçues',
            'demande' => $serviceRequest,
            'devis_existant' => $existingQuote,
            'peut_repondre' => null === $existingQuote && $serviceRequest->isOpen(),
        ]);
    }

    /**
     * Le montant saisi vient d'un champ texte de la maquette (virgule
     * française possible) : NUMERIC(12,2) en base n'accepte qu'un point.
     */
    private function normalizeAmount(string $amount): string
    {
        return str_replace(',', '.', $amount);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function currentProfile(): ProviderProfile
    {
        $user = $this->currentUser();

        $profile = $this->providerProfiles->findOneByUser($user);

        if (null === $profile) {
            throw $this->createAccessDeniedException('Aucun dossier prestataire rattaché à ce compte.');
        }

        return $profile;
    }

    private function findOrFail(string $id): ServiceRequest
    {
        $serviceRequest = Ulid::isValid($id) ? $this->requests->find(Ulid::fromString($id)) : null;

        if (null === $serviceRequest) {
            throw new NotFoundHttpException('Cette demande est introuvable.');
        }

        return $serviceRequest;
    }

    /**
     * @param list<ServiceRequest> $open
     *
     * @return list<string>
     */
    private function alreadyQuotedIds(ProviderProfile $profile, array $open): array
    {
        $ids = [];
        foreach ($open as $serviceRequest) {
            if (null !== $this->quotes->findOneByRequestAndProvider($serviceRequest, $profile)) {
                $ids[] = (string) $serviceRequest->getId();
            }
        }

        return $ids;
    }
}
