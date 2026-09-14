<?php

declare(strict_types=1);

namespace App\Payment\Controller;

use App\Payment\Repository\SubscriptionPlanRepository;
use App\Payment\Repository\SubscriptionRepository;
use App\Payment\Security\SubscriptionVoter;
use App\Payment\Service\SubscriptionService;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Repository\ProviderProfileRepository;
use App\Shared\Service\AccountIdentityPresenter;
use App\User\Entity\User;
use App\User\StaticAccount;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Espace professionnel — abonnement (§17 du CDC, route indicative
 * `/pro/abonnement` du §33.1).
 *
 * Seul modèle de revenu que le CDC autorise (§1.2, §3.2) : les prestations et
 * les activités privées ne sont jamais encaissées par la plateforme.
 */
#[IsGranted('ROLE_PROVIDER')]
final class SubscriptionController extends AbstractController
{
    public function __construct(
        private readonly ProviderProfileRepository $providerProfiles,
        private readonly SubscriptionPlanRepository $plans,
        private readonly SubscriptionRepository $subscriptions,
        private readonly SubscriptionService $service,
        private readonly AccountIdentityPresenter $identity,
    ) {
    }

    #[Route(path: ['fr' => '/pro/abonnement', 'en' => '/en/pro/subscription'], name: 'app_pro_subscription')]
    public function index(): Response
    {
        $provider = $this->currentProvider();
        $current = $this->service->currentFor($provider);

        if (null !== $current) {
            $this->denyAccessUnlessGranted(SubscriptionVoter::VIEW, $current);
        }

        return $this->render('subscription/index.html.twig', [
            'user' => $this->identity->identityFor($this->currentUser()),
            'menu' => StaticAccount::providerMenu(),
            'active' => 'Abonnement',
            'subscription' => $current,
            'plans' => $this->plans->findActive(),
            // Historique (§17.2 du CDC) : les précédentes lignes, résiliées ou
            // remplacées — voir le commentaire de l'entité Subscription.
            'history' => array_filter(
                $this->subscriptions->findHistoryFor($provider),
                static fn ($s) => $s !== $current,
            ),
        ]);
    }

    #[Route(path: ['fr' => '/pro/abonnement/{slug}/souscrire', 'en' => '/en/pro/subscription/{slug}/subscribe'], name: 'app_pro_subscription_subscribe', methods: ['POST'])]
    public function subscribe(string $slug, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return $this->redirectToRoute('app_pro_subscription');
        }

        $plan = $this->plans->findOneBy(['slug' => $slug, 'active' => true]);

        if (null === $plan) {
            $this->addFlash('error', 'Cette offre n\'est plus disponible.');

            return $this->redirectToRoute('app_pro_subscription');
        }

        try {
            $result = $this->service->startCheckout(
                $this->currentProvider(),
                $plan,
                $this->generateUrl('app_pro_subscription_success', referenceType: UrlGeneratorInterface::ABSOLUTE_URL),
                $this->generateUrl('app_pro_subscription_cancelled', referenceType: UrlGeneratorInterface::ABSOLUTE_URL),
            );
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_pro_subscription');
        }

        return $this->redirect($result->redirectUrl);
    }

    #[Route(path: ['fr' => '/pro/abonnement/succes', 'en' => '/en/pro/subscription/success'], name: 'app_pro_subscription_success')]
    public function success(): Response
    {
        return $this->render('subscription/succes.html.twig', [
            'subscription' => $this->service->currentFor($this->currentProvider()),
        ]);
    }

    #[Route(path: ['fr' => '/pro/abonnement/annule', 'en' => '/en/pro/subscription/cancelled'], name: 'app_pro_subscription_cancelled')]
    public function cancelled(): Response
    {
        return $this->render('subscription/annule.html.twig');
    }

    #[Route(path: ['fr' => '/pro/abonnement/resilier', 'en' => '/en/pro/subscription/cancel'], name: 'app_pro_subscription_cancel', methods: ['POST'])]
    public function cancel(Request $request): Response
    {
        $provider = $this->currentProvider();
        $current = $this->service->currentFor($provider);

        if (null === $current) {
            throw $this->createNotFoundException('Aucun abonnement en cours.');
        }

        $this->denyAccessUnlessGranted(SubscriptionVoter::MANAGE, $current);

        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return $this->redirectToRoute('app_pro_subscription');
        }

        $this->service->cancel($current, $provider, atPeriodEnd: true);
        $this->addFlash('success', 'Votre abonnement sera résilié à la fin de la période en cours.');

        return $this->redirectToRoute('app_pro_subscription');
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
