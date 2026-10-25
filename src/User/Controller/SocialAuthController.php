<?php

declare(strict_types=1);

namespace App\User\Controller;

use App\Legal\Service\CookieConsentService;
use App\User\Enum\SocialProvider;
use App\User\OAuth\OAuthException;
use App\User\OAuth\OAuthProviderRegistry;
use App\User\OAuth\SocialUser;
use App\User\Service\SocialEmailChallenge;
use App\User\Service\SocialEmailVerificationRequired;
use App\User\Service\SocialLoginException;
use App\User\Service\SocialLoginService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;

/**
 * Connexion Google/Facebook réservée aux clients.
 *
 * Deux routes par fournisseur : le départ, qui redirige vers le service, et le
 * retour, où l'on échange le code contre une identité.
 *
 * Le state aléatoire lie le retour à la session et au fournisseur pendant
 * dix minutes. Il est consommé avant tout échange du code côté serveur.
 */
final class SocialAuthController extends AbstractController
{
    private const SESSION_STATE = 'oauth_state';
    private const SESSION_NONCE = 'oauth_nonce';
    private const SESSION_PROVIDER = 'oauth_provider';
    private const SESSION_STARTED = 'oauth_started';

    public function __construct(
        private readonly OAuthProviderRegistry $registry,
        private readonly SocialLoginService $socialLogin,
        private readonly CookieConsentService $cookieConsent,
        private readonly LoggerInterface $logger,
        private readonly SocialEmailChallenge $emailChallenge,
        #[Autowire(service: 'limiter.social_login')] private readonly RateLimiterFactory $limiter,
    ) {
    }

    /**
     * Départ : redirige vers le fournisseur.
     */
    // Les deux routes sociales gardent un chemin unique : leur adresse de
    // retour est enregistrée chez Google et Facebook. La traduire
    // invaliderait les connexions sociales du jour au lendemain.
    #[Route('/connexion/{service}', name: 'app_social_start', requirements: ['service' => 'google|facebook'], methods: ['GET'])]
    public function start(Request $request, string $service): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute($this->isGranted('ROLE_ADMIN') ? 'admin' : ($this->isGranted('ROLE_PROVIDER') ? 'app_pro_dashboard' : 'app_account_dashboard'));
        }

        $provider = SocialProvider::from($service);
        $client = $this->registry->get($provider);

        if (!$client->isConfigured()) {
            // Cas normal aujourd'hui : les identifiants d'application sont
            // encore ceux de démonstration. On le dit clairement plutôt que
            // d'envoyer l'utilisateur sur une erreur du fournisseur.
            $this->addFlash('error', sprintf(
                'La connexion avec %s n\'est pas encore activée sur cette installation.',
                $provider->label(),
            ));

            return $this->redirectToRoute('app_login');
        }

        if (!$this->limiter->create((string) $request->getClientIp())->consume()->isAccepted()) {
            $this->addFlash('error', 'Trop de demandes. Réessayez dans quelques minutes.');

            return $this->redirectToRoute('app_login');
        }

        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));

        $session = $request->getSession();
        $session->remove('social_email_challenge');
        $session->set(self::SESSION_STARTED, time());
        $session->set(self::SESSION_STATE, $state);
        $session->set(self::SESSION_NONCE, $nonce);
        $session->set(self::SESSION_PROVIDER, $provider->value);

        return $this->redirect($client->authorizationUrl($state, $nonce));
    }

    /** Retour Google/Facebook : code d'autorisation reçu en GET. */
    #[Route('/connexion/{service}/retour', name: 'app_social_check', requirements: ['service' => 'google|facebook'], methods: ['GET'])]
    public function check(Request $request, string $service, Security $security): Response
    {
        $provider = SocialProvider::from($service);
        $client = $this->registry->get($provider);
        $session = $request->getSession();

        $started = $session->remove(self::SESSION_STARTED);
        $expectedState = $session->get(self::SESSION_STATE);
        $nonce = (string) $session->get(self::SESSION_NONCE, '');
        $expectedProvider = $session->get(self::SESSION_PROVIDER);

        $receivedState = $request->query->get('state');

        // Les valeurs sont retirées AVANT tout traitement : quel que soit
        // le résultat, elles ne doivent jamais resservir.
        $session->remove(self::SESSION_STATE);
        $session->remove(self::SESSION_NONCE);
        $session->remove(self::SESSION_PROVIDER);

        if (!\is_string($expectedState) || '' === $expectedState
            || !\is_string($receivedState)
            || !hash_equals($expectedState, $receivedState)
            || $expectedProvider !== $provider->value
            || !\is_int($started) || $started < time() - 600
        ) {
            // hash_equals plutôt que « === » : comparaison à temps constant,
            // qui ne laisse pas deviner la valeur attendue caractère par
            // caractère.
            $this->addFlash('error', 'La connexion a expiré ou n\'a pas pu être vérifiée. Merci de recommencer.');

            return $this->redirectToRoute('app_login');
        }

        if ($this->getUser()) {
            return $this->redirectToRoute('app_account_dashboard');
        }

        try {
            return $this->complete($request, $client->fetchUser($request, $nonce), $security);
        } catch (OAuthException|HttpClientException $e) {
            return $this->providerFailure($provider, $e);
        }
    }

    #[Route('/connexion/confirmation-email', name: 'app_social_email', methods: ['GET', 'POST'])]
    public function confirmEmail(Request $request, Security $security): Response
    {
        $email = $this->emailChallenge->email($request->getSession());
        if (null === $email || $this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Votre session a expiré, merci de recommencer.');

                return $this->redirectToRoute('app_social_email');
            }
            $identity = $this->emailChallenge->confirm($request->getSession(), (string) $request->request->get('code'));
            if (null !== $identity) {
                return $this->complete($request, $identity, $security);
            }
            $this->addFlash('error', 'Ce code est incorrect ou périmé. Recommencez la connexion si nécessaire.');

            return $this->redirectToRoute('app_social_email');
        }

        return $this->render('security/social_email.html.twig', ['email' => $email]);
    }

    private function complete(Request $request, SocialUser $identity, Security $security): Response
    {
        try {
            try {
                $user = $this->socialLogin->resolve($identity, $request);
            } catch (SocialEmailVerificationRequired) {
                $this->emailChallenge->start($request->getSession(), $identity);

                return $this->redirectToRoute('app_social_email');
            }
            // Le firewall possède aussi remember_me : nommer form_login évite
            // l'ambiguïté et réutilise le contrôle du compte et le success handler.
            $response = $security->login($user, 'form_login', 'main');
        } catch (SocialLoginException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_login');
        } catch (AuthenticationException) {
            $this->addFlash('error', 'Ce compte ne peut pas se connecter. Vérifiez votre adresse e-mail ou contactez-nous.');

            return $this->redirectToRoute('app_login');
        } catch (TransportExceptionInterface|UniqueConstraintViolationException $e) {
            return $this->providerFailure($identity->provider, $e);
        }

        $this->cookieConsent->linkToUser($request, $user);
        $this->addFlash('success', sprintf('Vous êtes connecté avec %s.', $identity->provider->label()));

        return $response ?? $this->redirectToRoute('app_account_dashboard');
    }

    private function providerFailure(SocialProvider $provider, \Throwable $error): Response
    {
        $this->logger->error('Échec de connexion sociale.', ['fournisseur' => $provider->value, 'type' => $error::class]);
        $this->addFlash('error', sprintf('La connexion avec %s a échoué. Merci de réessayer ou d’utiliser votre mot de passe.', $provider->label()));

        return $this->redirectToRoute('app_login');
    }
}
