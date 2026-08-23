<?php

declare(strict_types=1);

namespace App\User\Controller;

use App\Legal\Service\CookieConsentService;
use App\User\Enum\SocialProvider;
use App\User\OAuth\OAuthException;
use App\User\OAuth\OAuthProviderRegistry;
use App\User\Service\SocialLoginException;
use App\User\Service\SocialLoginService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Connexion par Google, Facebook ou « Se connecter avec Apple ».
 *
 * Deux routes par fournisseur : le départ, qui redirige vers le service, et le
 * retour, où l'on échange le code contre une identité.
 *
 * Deux valeurs à usage unique protègent l'aller-retour :
 *  - « state » : tiré au sort au départ, comparé au retour. C'est la protection
 *    contre la falsification de requête — sans elle, un tiers peut faire
 *    aboutir SA connexion dans le navigateur de quelqu'un d'autre.
 *  - « nonce » : lie le jeton d'identité à CETTE demande, et interdit de
 *    rejouer un jeton obtenu ailleurs.
 *
 * Les deux vivent en session et sont effacés dès qu'ils ont servi.
 */
final class SocialAuthController extends AbstractController
{
    private const SESSION_STATE = 'oauth_state';
    private const SESSION_NONCE = 'oauth_nonce';
    private const SESSION_PROVIDER = 'oauth_provider';

    public function __construct(
        private readonly OAuthProviderRegistry $registry,
        private readonly SocialLoginService $socialLogin,
        private readonly CookieConsentService $cookieConsent,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Départ : redirige vers le fournisseur.
     */
    // Les deux routes sociales gardent un chemin unique : leur adresse de
    // retour est enregistree chez Google, Facebook et Apple. La traduire
    // invaliderait les connexions sociales du jour au lendemain.
    #[Route('/connexion/{service}', name: 'app_social_start', requirements: ['service' => 'google|facebook|apple'], methods: ['GET'])]
    public function start(Request $request, string $service): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
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

        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));

        $session = $request->getSession();
        $session->set(self::SESSION_STATE, $state);
        $session->set(self::SESSION_NONCE, $nonce);
        $session->set(self::SESSION_PROVIDER, $provider->value);

        return $this->redirect($client->authorizationUrl($state, $nonce));
    }

    /**
     * Retour du fournisseur.
     *
     * GET et POST sont acceptés : Apple répond en POST dès qu'on lui demande le
     * nom ou l'e-mail, les deux autres en GET.
     */
    #[Route('/connexion/{service}/retour', name: 'app_social_check', requirements: ['service' => 'google|facebook|apple'], methods: ['GET', 'POST'])]
    public function check(Request $request, string $service, Security $security): Response
    {
        $provider = SocialProvider::from($service);
        $client = $this->registry->get($provider);
        $session = $request->getSession();

        $expectedState = $session->get(self::SESSION_STATE);
        $nonce = (string) $session->get(self::SESSION_NONCE, '');
        $expectedProvider = $session->get(self::SESSION_PROVIDER);

        // Le « state » revient en GET ou en POST selon le fournisseur.
        $receivedState = $request->request->get('state') ?? $request->query->get('state');

        // Les trois valeurs sont retirées AVANT tout traitement : quel que soit
        // le résultat, elles ne doivent jamais resservir.
        $session->remove(self::SESSION_STATE);
        $session->remove(self::SESSION_NONCE);
        $session->remove(self::SESSION_PROVIDER);

        if (!\is_string($expectedState) || '' === $expectedState
            || !\is_string($receivedState)
            || !hash_equals($expectedState, $receivedState)
            || $expectedProvider !== $provider->value
        ) {
            // hash_equals plutôt que « === » : comparaison à temps constant,
            // qui ne laisse pas deviner la valeur attendue caractère par
            // caractère.
            $this->addFlash('error', 'La connexion a expiré ou n\'a pas pu être vérifiée. Merci de recommencer.');

            return $this->redirectToRoute('app_login');
        }

        try {
            $socialUser = $client->fetchUser($request, $nonce);
            $user = $this->socialLogin->resolve($socialUser, $request);
        } catch (SocialLoginException $e) {
            // Message pensé pour l'utilisateur : on l'affiche tel quel.
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_login');
        } catch (OAuthException $e) {
            // Panne technique : le détail part dans les journaux, l'utilisateur
            // n'a pas à lire la réponse brute d'un fournisseur.
            $this->logger->error('Échec de connexion sociale.', [
                'fournisseur' => $provider->value,
                'exception' => $e,
            ]);

            $this->addFlash('error', sprintf(
                'La connexion avec %s a échoué. Merci de réessayer ou d\'utiliser votre mot de passe.',
                $provider->label(),
            ));

            return $this->redirectToRoute('app_login');
        }

        $security->login($user);

        // Le choix de cookies fait avant la connexion est rattaché au compte.
        $this->cookieConsent->linkToUser($request, $user);

        $this->addFlash('success', sprintf('Vous êtes connecté avec %s.', $provider->label()));

        return $this->redirectToRoute('app_home');
    }
}
