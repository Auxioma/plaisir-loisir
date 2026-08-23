<?php

declare(strict_types=1);

namespace App\User\Controller;

use App\User\Enum\AccountType;
use App\User\Form\RegistrationFormType;
use App\User\Service\PasswordResetService;
use App\User\Service\RegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Contrôleur d'authentification : login, logout, inscription.
 */
final class SecurityController extends AbstractController
{
    /**
     * Affiche le formulaire de connexion et transmet les erreurs éventuelles.
     */
    // Cible du formulaire de connexion, referencee par le pare-feu
    // (config/packages/security.yaml) et interdite d'indexation : elle reste
    // unique. La page visible, elle, est /authentification et est traduite.
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Si l'utilisateur est déjà connecté, on le redirige vers l'accueil.
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    /**
     * Route interceptée par le firewall ; le corps n'est jamais exécuté.
     */
    // Interceptee par le pare-feu avant d'arriver ici : chemin unique impose.
    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * Tout premier écran du flow d'authentification (maquette du 27/07) :
     * choix du profil — Professionnel / Prestataire à gauche, Client à droite.
     * Front navigable en attendant le back : chaque tuile mène à l'inscription
     * avec le type présélectionné.
     */
    #[Route(path: ['fr' => '/authentification', 'en' => '/en/authentication'], name: 'app_auth_choice')]
    public function authChoice(): Response
    {
        return $this->render('security/choice.html.twig');
    }

    /*
     * ------------------------------------------------------------------------
     *  Mot de passe oublié — 3 écrans de la maquette.
     *
     *  L'adresse saisie à l'étape 1, puis le code validé à l'étape 2, sont
     *  conservés en session : les trois écrans forment un seul parcours et
     *  rien ne doit transiter par l'URL, où l'adresse resterait dans
     *  l'historique du navigateur et dans les journaux du serveur.
     *
     *  Chaque étape refuse de s'afficher si la précédente n'a pas été
     *  franchie ; sinon il suffirait d'ouvrir directement le troisième écran.
     * ------------------------------------------------------------------------
     */

    /** Adresse en cours de réinitialisation. */
    private const SESSION_RESET_EMAIL = 'password_reset_email';

    /** Code déjà validé à l'étape 2, revérifié à l'étape 3. */
    private const SESSION_RESET_CODE = 'password_reset_code';

    /**
     * Étape 1/3 — saisie de l'adresse e-mail.
     */
    #[Route(path: ['fr' => '/mot-de-passe-oublie', 'en' => '/en/forgot-password'], name: 'app_forgot_password_request', methods: ['GET', 'POST'])]
    public function forgotPasswordRequest(Request $request, PasswordResetService $passwordReset): Response
    {
        $session = $request->getSession();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Votre session a expiré, merci de recommencer.');

                return $this->redirectToRoute('app_forgot_password_request');
            }

            $email = trim((string) $request->request->get('email'));

            if ('' === $email) {
                $this->addFlash('error', 'Veuillez saisir votre adresse e-mail.');

                return $this->redirectToRoute('app_forgot_password_request');
            }

            // Volontairement muet sur l'existence du compte : le même message
            // et le même écran suivant, que l'adresse soit connue ou non.
            $passwordReset->requestCode($email);

            $session->set(self::SESSION_RESET_EMAIL, $email);
            $session->remove(self::SESSION_RESET_CODE);

            return $this->redirectToRoute('app_forgot_password_code');
        }

        return $this->render('security/password_forgot.html.twig', [
            // Pré-remplie quand on revient de l'étape 2 par « Renvoyer ».
            'email' => (string) $session->get(self::SESSION_RESET_EMAIL, ''),
        ]);
    }

    /**
     * Étape 2/3 — vérification du code reçu par e-mail.
     */
    #[Route(path: ['fr' => '/mot-de-passe-oublie/verification', 'en' => '/en/forgot-password/verification'], name: 'app_forgot_password_code', methods: ['GET', 'POST'])]
    public function forgotPasswordCode(Request $request, PasswordResetService $passwordReset): Response
    {
        $session = $request->getSession();
        $email = (string) $session->get(self::SESSION_RESET_EMAIL, '');

        if ('' === $email) {
            return $this->redirectToRoute('app_forgot_password_request');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Votre session a expiré, merci de recommencer.');

                return $this->redirectToRoute('app_forgot_password_code');
            }

            $code = trim((string) $request->request->get('code'));

            if (!$passwordReset->verifyCode($email, $code)) {
                $this->addFlash('error', 'Ce code est incorrect ou périmé. Vérifiez votre e-mail ou demandez-en un nouveau.');

                return $this->redirectToRoute('app_forgot_password_code');
            }

            $session->set(self::SESSION_RESET_CODE, strtoupper($code));

            return $this->redirectToRoute('app_forgot_password_reset');
        }

        return $this->render('security/password_code.html.twig');
    }

    /**
     * Étape 3/3 — définition du nouveau mot de passe.
     */
    #[Route(path: ['fr' => '/mot-de-passe-oublie/nouveau', 'en' => '/en/forgot-password/new'], name: 'app_forgot_password_reset', methods: ['GET', 'POST'])]
    public function forgotPasswordReset(Request $request, PasswordResetService $passwordReset): Response
    {
        $session = $request->getSession();
        $email = (string) $session->get(self::SESSION_RESET_EMAIL, '');
        $code = (string) $session->get(self::SESSION_RESET_CODE, '');

        // Sans code validé, ce troisième écran n'a pas à s'afficher.
        if ('' === $email || '' === $code) {
            return $this->redirectToRoute('app_forgot_password_request');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Votre session a expiré, merci de recommencer.');

                return $this->redirectToRoute('app_forgot_password_reset');
            }

            $password = (string) $request->request->get('password');
            $confirm = (string) $request->request->get('passwordConfirm');

            if (mb_strlen($password) < 8) {
                $this->addFlash('error', 'Le mot de passe doit faire au moins 8 caractères.');

                return $this->redirectToRoute('app_forgot_password_reset');
            }

            if ($password !== $confirm) {
                $this->addFlash('error', 'Les deux mots de passe ne correspondent pas.');

                return $this->redirectToRoute('app_forgot_password_reset');
            }

            if (!$passwordReset->reset($email, $code, $password)) {
                // Le code a expiré entre l'étape 2 et l'étape 3.
                $session->remove(self::SESSION_RESET_CODE);
                $this->addFlash('error', 'Votre code a expiré. Merci de recommencer la procédure.');

                return $this->redirectToRoute('app_forgot_password_request');
            }

            $session->remove(self::SESSION_RESET_EMAIL);
            $session->remove(self::SESSION_RESET_CODE);

            $this->addFlash('success', 'Votre mot de passe a été modifié. Vous pouvez vous connecter.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/password_reset.html.twig');
    }

    /**
     * Inscription d'un nouvel utilisateur via formulaire Twig.
     *
     * Les champs sont ceux de la maquette (nom & prénom, e-mail, téléphone,
     * mot de passe, conditions générales) : voir RegistrationFormType.
     */
    // /register etait un chemin anglais servant une page francaise. Il
    // devient /inscription ; l'ancienne adresse survit ci-dessous en
    // redirection permanente, le temps que les liens deja partages et
    // l'index des moteurs de recherche suivent.
    #[Route(path: ['fr' => '/inscription', 'en' => '/en/signup'], name: 'app_register')]
    public function register(Request $request, RegistrationService $registrationService): Response
    {
        // Si l'utilisateur est déjà connecté, on le redirige vers l'accueil.
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        // Le type vient de l'écran de choix (« /register?type=pro ») et n'existe
        // que dans l'URL du GET : il est recopié dans un champ caché pour
        // survivre à l'envoi du formulaire.
        $form = $this->createForm(RegistrationFormType::class, [
            'accountType' => AccountType::fromInput($request->query->get('type'))->value,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $user = $registrationService->register(
                    (string) $form->get('fullName')->getData(),
                    (string) $form->get('email')->getData(),
                    (string) $form->get('password')->getData(),
                    $form->get('phone')->getData(),
                    AccountType::fromInput($form->get('accountType')->getData()),
                );

                // Un professionnel doit savoir que son dossier est ouvert mais
                // pas encore complet : sans cela, il croirait pouvoir publier.
                $this->addFlash('success', \in_array('ROLE_PROVIDER', $user->getRoles(), true)
                    ? 'Votre compte professionnel a été créé. Connectez-vous pour compléter votre dossier prestataire.'
                    : 'Votre compte a été créé avec succès. Connectez-vous pour continuer.');

                return $this->redirectToRoute('app_login');
            } catch (ConflictHttpException) {
                // Sans ce filet, un e-mail déjà pris affichait une page
                // d'erreur HTTP 409 au lieu du formulaire.
                $this->addFlash('error', 'Un compte existe déjà avec cet e-mail. Connectez-vous ou utilisez une autre adresse.');
            }
        }

        // La maquette ne prévoit aucun emplacement pour un message d'erreur
        // sous les champs. Tant qu'elle n'en fournit pas, on remonte les
        // erreurs de validation dans le bandeau flash commun (base.html.twig)
        // plutôt que d'inventer du balisage dans la carte.
        $this->flashFormErrors($form);

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    /**
     * Recopie les erreurs de validation d'un formulaire dans les messages flash.
     */
    private function flashFormErrors(FormInterface $form): void
    {
        if (!$form->isSubmitted()) {
            return;
        }

        // true : on veut aussi les erreurs portées par les champs enfants,
        // pas seulement celles du formulaire lui-même.
        foreach ($form->getErrors(true) as $error) {
            $this->addFlash('error', $error->getMessage());
        }
    }
}
