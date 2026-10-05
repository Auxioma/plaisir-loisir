<?php

declare(strict_types=1);

namespace App\Quote\Controller;

use App\Catalog\Entity\Category;
use App\Catalog\Enum\ServiceStatus;
use App\Catalog\Repository\ServiceRepository;
use App\Provider\Controller\Space\AbstractProviderSpaceController;
use App\Provider\Entity\ProviderProfile;
use App\Quote\Entity\Quote;
use App\Quote\Entity\ServiceRequest;
use App\Quote\Repository\QuoteRepository;
use App\Quote\Repository\ServiceRequestRepository;
use App\Quote\Security\ServiceRequestVoter;
use App\Quote\Service\QuoteService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

/**
 * « Demandes reçues » du professionnel (§10, §11 du CDC), dans son espace
 * pro depuis le 05/10 — elles s'affichaient dans la coquille de l'espace
 * particulier, avec un menu pro recopié.
 *
 * Il voit les demandes ouvertes de SES catégories : sa catégorie principale
 * et celles de ses activités publiées. Seul un professionnel VÉRIFIÉ peut
 * déposer un devis (ServiceRequestVoter::SUBMIT_QUOTE).
 */
#[IsGranted('ROLE_PROVIDER')]
final class ProviderRequestController extends AbstractProviderSpaceController
{
    public function __construct(
        private readonly ServiceRequestRepository $requests,
        private readonly QuoteRepository $quotes,
        private readonly QuoteService $quoteService,
        private readonly ServiceRepository $services,
    ) {
    }

    #[Route(path: ['fr' => '/pro/demandes', 'en' => '/en/pro/requests'], name: 'app_pro_requests')]
    public function index(Request $request): Response
    {
        $provider = $this->currentProvider();
        $open = $this->requests->findOpenForCategories($this->categoriesOf($provider));
        $myQuotes = [];
        foreach ($open as $serviceRequest) {
            if (null !== ($quote = $this->quotes->findOneByRequestAndProvider($serviceRequest, $provider))) {
                $myQuotes[(string) $serviceRequest->getId()] = $quote;
            }
        }
        $sent = $this->quotes->findBy(['provider' => $provider], ['createdAt' => 'DESC']);

        $tab = \in_array($request->query->get('onglet'), ['envoyes', 'toutes'], true) ? (string) $request->query->get('onglet') : 'a-traiter';
        $rows = match ($tab) {
            'toutes' => $open,
            'envoyes' => array_values(array_filter(array_map(static fn (Quote $q): ?ServiceRequest => $q->getServiceRequest(), $sent))),
            default => array_values(array_filter($open, static fn (ServiceRequest $r): bool => !isset($myQuotes[(string) $r->getId()]))),
        };
        $sentByRequest = [];
        foreach ($sent as $quote) {
            $sentByRequest[(string) $quote->getServiceRequest()?->getId()] = $quote;
        }

        return $this->renderSpace('provider/space/requests.html.twig', '', [
            'requests' => $rows,
            'tab' => $tab,
            'my_quotes' => $sentByRequest,
            'counts' => [
                'todo' => \count($open) - \count($myQuotes),
                'sent' => \count($sent),
                'open' => \count($open),
                'accepted' => \count(array_filter($sent, static fn (Quote $q): bool => 'accepted' === $q->getStatus()->value)),
            ],
            'verified' => 'verified' === $provider->getStatus()->value,
        ]);
    }

    #[Route(path: ['fr' => '/pro/demandes/{id}', 'en' => '/en/pro/requests/{id}'], name: 'app_pro_requests_show', methods: ['GET', 'POST'])]
    public function show(string $id, Request $request): Response
    {
        $serviceRequest = $this->findOrFail($id);
        $this->denyAccessUnlessGranted(ServiceRequestVoter::VIEW, $serviceRequest);

        $provider = $this->currentProvider();
        $existingQuote = $this->quotes->findOneByRequestAndProvider($serviceRequest, $provider);
        $errors = [];
        $values = ['montant' => '', 'message' => ''];

        if ($request->isMethod('POST')) {
            $this->denyAccessUnlessGranted(ServiceRequestVoter::SUBMIT_QUOTE, $serviceRequest);
            $values = ['montant' => trim((string) $request->request->get('montant', '')), 'message' => trim((string) $request->request->get('message', ''))];

            if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
                $errors['montant'] = 'Votre session a expiré, merci de réessayer.';
            }
            $amount = str_replace([',', ' ', '€'], ['.', '', ''], $values['montant']);
            if (!is_numeric($amount) || (float) $amount <= 0 || (float) $amount > 1000000) {
                $errors['montant'] = 'Indiquez un montant valide (en euros).';
            }
            if (mb_strlen($values['message']) > 3000) {
                $errors['message'] = '3000 caractères maximum.';
            }

            if ([] === $errors) {
                try {
                    $this->quoteService->submitQuote($serviceRequest, $provider, number_format((float) $amount, 2, '.', ''), '' !== $values['message'] ? $values['message'] : null);
                    $this->addFlash('success', 'Votre devis a bien été envoyé au client.');

                    return $this->redirectToRoute('app_pro_requests_show', ['id' => $id]);
                } catch (\InvalidArgumentException $e) {
                    $errors['montant'] = $e->getMessage();
                }
            }
        }

        return $this->renderSpace('provider/space/request_show.html.twig', '', [
            'demande' => $serviceRequest,
            'devis_existant' => $existingQuote,
            'peut_repondre' => null === $existingQuote && $serviceRequest->isOpen() && $this->isGranted(ServiceRequestVoter::SUBMIT_QUOTE, $serviceRequest),
            'errors' => $errors,
            'values' => $values,
            'competitors' => max(0, $serviceRequest->getQuotes()->count() - (null !== $existingQuote ? 1 : 0)),
        ], new Response(null, [] === $errors ? 200 : 422));
    }

    /**
     * Catégorie principale + catégories des activités publiées.
     *
     * @return list<Category>
     */
    private function categoriesOf(ProviderProfile $provider): array
    {
        $categories = [];
        if (null !== ($main = $provider->getMainCategory())) {
            $categories[(string) $main->getId()] = $main;
        }
        foreach ($this->services->findForProvider($provider) as $service) {
            if (ServiceStatus::Published === $service->getStatus() && null !== ($c = $service->getCategory())) {
                $categories[(string) $c->getId()] = $c;
            }
        }

        return array_values($categories);
    }

    private function findOrFail(string $id): ServiceRequest
    {
        $serviceRequest = Ulid::isValid($id) ? $this->requests->find(Ulid::fromString($id)) : null;

        if (null === $serviceRequest) {
            throw new NotFoundHttpException('Cette demande est introuvable.');
        }

        return $serviceRequest;
    }
}
