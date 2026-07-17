<?php

declare(strict_types=1);

namespace App\User\Controller;

use App\User\Form\RegistrationFormType;
use App\User\Service\RegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /*
     * ------------------------------------------------------------------------
     *  Mot de passe oublié (3 étapes).
     *  ⚠️ Pour l'instant, ces routes se contentent d'AFFICHER les écrans.
     *  La logique métier (génération/envoi du code par e-mail, vérification,
     *  enregistrement du nouveau mot de passe) sera ajoutée à la phase de
     *  câblage front/back.
     * ------------------------------------------------------------------------
     */

    #[Route('/mot-de-passe-oublie', name: 'app_forgot_password_request')]
    public function forgotPasswordRequest(): Response
    {
        return $this->render('security/password_forgot.html.twig');
    }

    #[Route('/mot-de-passe-oublie/verification', name: 'app_forgot_password_code')]
    public function forgotPasswordCode(): Response
    {
        return $this->render('security/password_code.html.twig');
    }

    #[Route('/mot-de-passe-oublie/nouveau', name: 'app_forgot_password_reset')]
    public function forgotPasswordReset(): Response
    {
        return $this->render('security/password_reset.html.twig');
    }

    /**
     * Inscription d'un nouvel utilisateur via formulaire Twig.
     */
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, RegistrationService $registrationService): Response
    {
        // Si l'utilisateur est déjà connecté, on le redirige vers l'accueil.
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $form = $this->createForm(RegistrationFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $registrationService->register(
                $form->get('email')->getData(),
                $form->get('plainPassword')->getData(),
                $form->get('firstName')->getData(),
                $form->get('lastName')->getData(),
            );

            $this->addFlash('success', 'Votre compte a été créé avec succès. Connectez-vous pour continuer.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
