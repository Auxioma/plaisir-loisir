<?php

declare(strict_types=1);

namespace App\Provider\Controller;

use App\Catalog\Repository\CategoryRepository;
use App\Payment\Service\SubscriptionService;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Repository\ProviderProfileRepository;
use App\Quote\Enum\QuoteStatus;
use App\Quote\Repository\QuoteRepository;
use App\Quote\Repository\ServiceRequestRepository;
use App\Shared\Service\AccountIdentityPresenter;
use App\User\Entity\User;
use App\User\StaticAccount;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Tableau de bord et profil professionnels (§8.3, routes indicatives
 * `/pro/tableau-de-bord` et `/pro/profil` du §33.1).
 *
 * POURQUOI CET ÉCRAN COMPTE
 * Signalé le 14/09 : un compte pro connecté voyait exactement la même page
 * qu'un client — parce que rien ne menait nulle part de spécifique. Les
 * pièces existaient déjà séparément (demandes reçues, abonnement, fiche
 * publique) : ce contrôleur les réunit sur un seul écran d'accueil, et
 * ajoute ce qui manquait encore pour un usage réel — modifier SA PROPRE
 * fiche sans passer par le back-office.
 *
 * CE QUI RESTE HORS PÉRIMÈTRE ICI
 * « Conversations actives » et « Avis reçus » (§8.3 du CDC) ont depuis reçu
 * leur propre écran (Lot G : ConversationController ; Lot H :
 * ReviewController) plutôt que d'être ajoutés ici — chacun mérite plus qu'un
 * chiffre sur ce tableau de bord.
 */
#[IsGranted('ROLE_PROVIDER')]
final class ProviderDashboardController extends AbstractController
{
    public function __construct(
        private readonly ProviderProfileRepository $providerProfiles,
        private readonly ServiceRequestRepository $requests,
        private readonly QuoteRepository $quotes,
        private readonly CategoryRepository $categories,
        private readonly SubscriptionService $subscriptions,
        private readonly EntityManagerInterface $entityManager,
        private readonly AccountIdentityPresenter $identity,
    ) {
    }

    #[Route(path: ['fr' => '/pro/tableau-de-bord', 'en' => '/en/pro/dashboard'], name: 'app_pro_dashboard')]
    public function dashboard(): Response
    {
        $provider = $this->currentProvider();
        $category = $provider->getMainCategory();

        $openRequests = null !== $category ? $this->requests->findOpenForCategory($category) : [];
        $sentQuotes = $this->quotes->findByProvider($provider);

        return $this->render('provider/tableau_de_bord.html.twig', [
            'user' => $this->identity->identityFor($this->currentUser()),
            'menu' => StaticAccount::providerMenu(),
            'active' => 'Tableau de bord',
            'provider' => $provider,
            'open_requests_count' => \count($openRequests),
            'sent_quotes' => $sentQuotes,
            'accepted_quotes_count' => \count(array_filter($sentQuotes, static fn ($q) => QuoteStatus::Accepted === $q->getStatus())),
            'pending_quotes_count' => \count(array_filter($sentQuotes, static fn ($q) => QuoteStatus::Pending === $q->getStatus())),
            'subscription' => $this->subscriptions->currentFor($provider),
        ]);
    }

    #[Route(path: ['fr' => '/pro/profil', 'en' => '/en/pro/profile'], name: 'app_pro_profile_edit', methods: ['GET', 'POST'])]
    public function editProfile(Request $request): Response
    {
        $provider = $this->currentProvider();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

                return $this->redirectToRoute('app_pro_profile_edit');
            }

            $displayName = trim((string) $request->request->get('displayName', ''));
            $categorySlug = (string) $request->request->get('metier', '');

            if ('' === $displayName) {
                $this->addFlash('error', 'Le nom affiché ne peut pas être vide.');

                return $this->redirectToRoute('app_pro_profile_edit');
            }

            $provider
                ->setDisplayName(mb_substr($displayName, 0, 120))
                ->setBio($this->nullIfEmpty((string) $request->request->get('bio', ''), 4000))
                ->setCompanyName($this->nullIfEmpty((string) $request->request->get('companyName', ''), 180))
                ->setCity($this->nullIfEmpty((string) $request->request->get('ville', ''), 120))
                ->setWebsiteUrl($this->nullIfEmpty((string) $request->request->get('websiteUrl', ''), 255))
                ->setFacebookUrl($this->nullIfEmpty((string) $request->request->get('facebookUrl', ''), 255))
                ->setInstagramUrl($this->nullIfEmpty((string) $request->request->get('instagramUrl', ''), 255))
                ->setLinkedinUrl($this->nullIfEmpty((string) $request->request->get('linkedinUrl', ''), 255));

            if ('' !== $categorySlug) {
                $category = $this->categories->findOneBy(['slug' => $categorySlug]);
                if (null !== $category) {
                    $provider->setMainCategory($category);
                }
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Votre fiche a été mise à jour.');

            return $this->redirectToRoute('app_pro_profile_edit');
        }

        return $this->render('provider/profil_edition.html.twig', [
            'user' => $this->identity->identityFor($this->currentUser()),
            'menu' => StaticAccount::providerMenu(),
            'active' => 'Ma fiche professionnelle',
            'provider' => $provider,
            'categories' => $this->categories->findRoots(),
        ]);
    }

    private function nullIfEmpty(string $value, int $maxLength): ?string
    {
        $value = trim($value);

        return '' !== $value ? mb_substr($value, 0, $maxLength) : null;
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
        $user = $this->currentUser();

        $provider = $this->providerProfiles->findOneByUser($user);

        if (null === $provider) {
            throw $this->createAccessDeniedException('Aucun dossier prestataire rattaché à ce compte.');
        }

        return $provider;
    }
}
