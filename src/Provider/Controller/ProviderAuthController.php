<?php

declare(strict_types=1);

namespace App\Provider\Controller;

use App\Catalog\Entity\Category;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderDocumentKind;
use App\Provider\Form\ProviderRegistrationFormType;
use App\Provider\Repository\ProviderDocumentRepository;
use App\Provider\Repository\ProviderProfileRepository;
use App\Provider\Service\ProviderDocumentStorage;
use App\Provider\Service\ProviderRegistrationService;
use App\User\Entity\User;
use App\User\Service\PasswordResetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Uid\Ulid;

/**
 * Parcours d'authentification de l'espace PROFESSIONNEL.
 *
 * POURQUOI UN CONTRÔLEUR SÉPARÉ DE SecurityController
 * Ce ne sont pas les mêmes écrans. Le parcours client tient sur le gabarit
 * « accroche + carte », le parcours professionnel sur deux autres : une bande
 * latérale violette listant les avantages (connexion, mot de passe oublié,
 * code) et un écran scindé en deux cartes (inscription en deux étapes). Les
 * champs diffèrent aussi — le professionnel déclare une activité et un siège
 * social. Partager un contrôleur aurait signifié un `if ($pro)` dans chacune
 * des sept méthodes.
 *
 * LA CONNEXION PASSE PAR LE MÊME PARE-FEU
 * Il n'y a qu'un pare-feu, donc qu'un seul `check_path` (/login). Le
 * formulaire professionnel y poste comme l'autre, en emportant un champ caché
 * `_failure_path` : sans lui, une erreur de mot de passe renverrait le
 * professionnel sur l'écran de connexion CLIENT, puisque le gestionnaire
 * d'échec de Symfony retombe par défaut sur `login_path`.
 */
final class ProviderAuthController extends AbstractController
{
    /** Dossier en cours de création, entre l'étape 1 et l'écran final. */
    private const SESSION_PROFILE = 'provider_registration_profile';

    /** Adresse en cours de réinitialisation (parcours professionnel). */
    private const SESSION_RESET_EMAIL = 'provider_password_reset_email';

    /** Code déjà validé, revérifié au moment de changer le mot de passe. */
    private const SESSION_RESET_CODE = 'provider_password_reset_code';

    /*
     * ------------------------------------------------------------------------
     *  Inscription — 3 écrans (Figma 955:91899, 956:110425, 956:113304).
     *
     *  Le dossier ouvert à l'étape 1 est mémorisé en session pour que l'étape 2
     *  sache à quoi rattacher les pièces. Rien ne transite par l'URL : un
     *  identifiant de dossier dans la barre d'adresse permettrait de déposer
     *  des documents dans le dossier d'un autre.
     * ------------------------------------------------------------------------
     */

    /**
     * Étape 1/2 — informations générales.
     */
    #[Route(
        path: ['fr' => '/pro/inscription', 'en' => '/en/pro/signup'],
        name: 'app_pro_register',
        methods: ['GET', 'POST'],
    )]
    public function register(Request $request, ProviderRegistrationService $registration): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $form = $this->createForm(ProviderRegistrationFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Category|null $category */
            $category = $form->get('mainCategory')->getData();

            try {
                $profile = $registration->registerFirstStep(
                    (string) $form->get('lastName')->getData(),
                    (string) $form->get('firstName')->getData(),
                    (string) $form->get('email')->getData(),
                    (string) $form->get('password')->getData(),
                    $form->get('phone')->getData(),
                    $category,
                    $form->get('registeredOffice')->getData(),
                );

                $request->getSession()->set(self::SESSION_PROFILE, (string) $profile->getId());

                return $this->redirectToRoute('app_pro_register_documents');
            } catch (ConflictHttpException) {
                $this->addFlash('error', 'Un compte existe déjà avec cet e-mail. Connectez-vous ou utilisez une autre adresse.');
            }
        }

        $this->flashFormErrors($form);

        return $this->render('provider/auth/register_infos.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    /**
     * Étape 2/2 — pièces justificatives.
     */
    #[Route(
        path: ['fr' => '/pro/inscription/documents', 'en' => '/en/pro/signup/documents'],
        name: 'app_pro_register_documents',
        methods: ['GET', 'POST'],
    )]
    public function registerDocuments(
        Request $request,
        ProviderProfileRepository $profiles,
        ProviderDocumentRepository $documents,
        ProviderDocumentStorage $storage,
        ProviderRegistrationService $registration,
    ): Response {
        $profile = $this->currentRegistration($request, $profiles);

        if (null === $profile) {
            return $this->redirectToRoute('app_pro_register');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Votre session a expiré, merci de recommencer.');

                return $this->redirectToRoute('app_pro_register_documents');
            }

            $refuses = [];

            // Les deux pièces nommées par la maquette.
            $attendus = [
                ProviderDocumentKind::OperatingLicence->value => ProviderDocumentKind::OperatingLicence,
                ProviderDocumentKind::FoodSafetyCertificate->value => ProviderDocumentKind::FoodSafetyCertificate,
            ];

            foreach ($attendus as $champ => $nature) {
                $fichier = $request->files->get($champ);

                if ($fichier instanceof UploadedFile) {
                    try {
                        $storage->store($profile, $fichier, $nature);
                    } catch (\InvalidArgumentException $refus) {
                        $refuses[] = $refus->getMessage();
                    }
                }
            }

            // « Ajouter un autre document » : autant de fichiers que le
            // prestataire en a ajoutés, d'où le nom de champ `others[]`.
            foreach ((array) ($request->files->all()['others'] ?? []) as $fichier) {
                if ($fichier instanceof UploadedFile) {
                    try {
                        $storage->store($profile, $fichier, ProviderDocumentKind::Other);
                    } catch (\InvalidArgumentException $refus) {
                        $refuses[] = $refus->getMessage();
                    }
                }
            }

            foreach ($refuses as $message) {
                $this->addFlash('error', $message);
            }

            if ([] !== $refuses) {
                return $this->redirectToRoute('app_pro_register_documents');
            }

            // Un dossier sans la moindre pièce n'a rien à faire en
            // vérification : le service client n'aurait rien à vérifier.
            if ([] === $documents->findForProfile($profile)) {
                $this->addFlash('error', 'Merci de déposer au moins un document avant de poursuivre.');

                return $this->redirectToRoute('app_pro_register_documents');
            }

            $registration->submitForVerification($profile);

            return $this->redirectToRoute('app_pro_register_done');
        }

        return $this->render('provider/auth/register_documents.html.twig', [
            'documents' => $documents->findForProfile($profile),
        ]);
    }

    /**
     * Écran de fin — « Votre demande a bien été enregistrée ».
     */
    #[Route(
        path: ['fr' => '/pro/inscription/confirmation', 'en' => '/en/pro/signup/confirmation'],
        name: 'app_pro_register_done',
        methods: ['GET'],
    )]
    public function registerDone(Request $request, ProviderProfileRepository $profiles): Response
    {
        $profile = $this->currentRegistration($request, $profiles);

        // Sans dossier soumis, cet écran féliciterait dans le vide.
        if (null === $profile) {
            return $this->redirectToRoute('app_pro_register');
        }

        // Le parcours est terminé : la session est libérée pour qu'un retour
        // sur /pro/inscription reparte d'un formulaire vierge.
        $request->getSession()->remove(self::SESSION_PROFILE);

        return $this->render('provider/auth/register_done.html.twig', [
            'profile' => $profile,
        ]);
    }

    /*
     * ------------------------------------------------------------------------
     *  Connexion (Figma 955:90119 — la variante avec bande latérale).
     * ------------------------------------------------------------------------
     */

    #[Route(
        path: ['fr' => '/pro/connexion', 'en' => '/en/pro/login'],
        name: 'app_pro_login',
        methods: ['GET'],
    )]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('provider/auth/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    /*
     * ------------------------------------------------------------------------
     *  Mot de passe oublié — 3 écrans, dont un absent de la maquette.
     *
     *  La maquette professionnelle s'arrête à la vue OTP. Un parcours qui
     *  vérifie un code puis ne propose jamais de nouveau mot de passe ne
     *  réinitialise rien : le troisième écran reprend donc le gabarit des deux
     *  autres, sans rien inventer de nouveau.
     *
     *  Les clés de session sont préfixées « provider_ » : elles ne doivent pas
     *  se mélanger à celles du parcours client, sinon commencer l'un puis
     *  l'autre validerait le code du mauvais compte.
     * ------------------------------------------------------------------------
     */

    #[Route(
        path: ['fr' => '/pro/mot-de-passe-oublie', 'en' => '/en/pro/forgot-password'],
        name: 'app_pro_forgot_password',
        methods: ['GET', 'POST'],
    )]
    public function forgotPassword(Request $request, PasswordResetService $passwordReset): Response
    {
        $session = $request->getSession();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Votre session a expiré, merci de recommencer.');

                return $this->redirectToRoute('app_pro_forgot_password');
            }

            $email = trim((string) $request->request->get('email'));

            if ('' === $email) {
                $this->addFlash('error', 'Veuillez saisir votre adresse e-mail.');

                return $this->redirectToRoute('app_pro_forgot_password');
            }

            // Volontairement muet sur l'existence du compte : même message et
            // même écran suivant, que l'adresse soit connue ou non.
            $passwordReset->requestCode($email);

            $session->set(self::SESSION_RESET_EMAIL, $email);
            $session->remove(self::SESSION_RESET_CODE);

            return $this->redirectToRoute('app_pro_forgot_password_code');
        }

        return $this->render('provider/auth/password_forgot.html.twig', [
            'email' => (string) $session->get(self::SESSION_RESET_EMAIL, ''),
        ]);
    }

    #[Route(
        path: ['fr' => '/pro/mot-de-passe-oublie/verification', 'en' => '/en/pro/forgot-password/verification'],
        name: 'app_pro_forgot_password_code',
        methods: ['GET', 'POST'],
    )]
    public function forgotPasswordCode(Request $request, PasswordResetService $passwordReset): Response
    {
        $session = $request->getSession();
        $email = (string) $session->get(self::SESSION_RESET_EMAIL, '');

        if ('' === $email) {
            return $this->redirectToRoute('app_pro_forgot_password');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Votre session a expiré, merci de recommencer.');

                return $this->redirectToRoute('app_pro_forgot_password_code');
            }

            $code = self::readCode($request);

            if (!$passwordReset->verifyCode($email, $code)) {
                $this->addFlash('error', 'Ce code est incorrect ou périmé. Vérifiez votre e-mail ou demandez-en un nouveau.');

                return $this->redirectToRoute('app_pro_forgot_password_code');
            }

            $session->set(self::SESSION_RESET_CODE, strtoupper($code));

            return $this->redirectToRoute('app_pro_forgot_password_reset');
        }

        return $this->render('provider/auth/password_code.html.twig', [
            'email' => $email,
            'codeLength' => PasswordResetService::CODE_LENGTH,
        ]);
    }

    #[Route(
        path: ['fr' => '/pro/mot-de-passe-oublie/nouveau', 'en' => '/en/pro/forgot-password/new'],
        name: 'app_pro_forgot_password_reset',
        methods: ['GET', 'POST'],
    )]
    public function forgotPasswordReset(Request $request, PasswordResetService $passwordReset): Response
    {
        $session = $request->getSession();
        $email = (string) $session->get(self::SESSION_RESET_EMAIL, '');
        $code = (string) $session->get(self::SESSION_RESET_CODE, '');

        // Sans code validé, ce troisième écran n'a pas à s'afficher.
        if ('' === $email || '' === $code) {
            return $this->redirectToRoute('app_pro_forgot_password');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Votre session a expiré, merci de recommencer.');

                return $this->redirectToRoute('app_pro_forgot_password_reset');
            }

            $password = (string) $request->request->get('password');
            $confirm = (string) $request->request->get('passwordConfirm');

            if (mb_strlen($password) < 8) {
                $this->addFlash('error', 'Le mot de passe doit faire au moins 8 caractères.');

                return $this->redirectToRoute('app_pro_forgot_password_reset');
            }

            if ($password !== $confirm) {
                $this->addFlash('error', 'Les deux mots de passe ne correspondent pas.');

                return $this->redirectToRoute('app_pro_forgot_password_reset');
            }

            if (!$passwordReset->reset($email, $code, $password)) {
                // Le code a expiré entre la vérification et la validation.
                $session->remove(self::SESSION_RESET_CODE);
                $this->addFlash('error', 'Votre code a expiré. Merci de recommencer la procédure.');

                return $this->redirectToRoute('app_pro_forgot_password');
            }

            $session->remove(self::SESSION_RESET_EMAIL);
            $session->remove(self::SESSION_RESET_CODE);

            $this->addFlash('success', 'Votre mot de passe a été modifié. Vous pouvez vous connecter.');

            return $this->redirectToRoute('app_pro_login');
        }

        return $this->render('provider/auth/password_reset.html.twig');
    }

    /*
     * ------------------------------------------------------------------------
     *  Utilitaires privés.
     * ------------------------------------------------------------------------
     */

    /**
     * Dossier en cours d'inscription : celui de la session, ou celui du
     * professionnel déjà connecté qui reviendrait finir son parcours.
     */
    private function currentRegistration(Request $request, ProviderProfileRepository $profiles): ?ProviderProfile
    {
        $identifiant = (string) $request->getSession()->get(self::SESSION_PROFILE, '');

        if ('' !== $identifiant && Ulid::isValid($identifiant)) {
            $profile = $profiles->find(Ulid::fromString($identifiant));

            if (null !== $profile) {
                return $profile;
            }
        }

        $utilisateur = $this->getUser();

        return $utilisateur instanceof User ? $profiles->findOneByUser($utilisateur) : null;
    }

    /**
     * Reconstitue le code depuis les cases de l'écran OTP.
     *
     * L'écran affiche une case par caractère : le navigateur poste donc
     * `code[]` et non `code`. On accepte aussi la forme simple, pour qu'un
     * collage direct ou un test fonctionnel n'aient pas à imiter le balisage.
     */
    private static function readCode(Request $request): string
    {
        $saisie = $request->request->all()['code'] ?? '';

        if (\is_array($saisie)) {
            $saisie = implode('', array_map(static fn (mixed $c): string => trim((string) $c), $saisie));
        }

        return trim((string) $saisie);
    }

    /**
     * Recopie les erreurs de validation dans les messages flash.
     *
     * Même parti pris que sur l'inscription client : la maquette ne prévoit
     * aucun emplacement pour un message sous les champs, on ne va pas inventer
     * du balisage dans la carte.
     */
    private function flashFormErrors(FormInterface $form): void
    {
        if (!$form->isSubmitted()) {
            return;
        }

        foreach ($form->getErrors(true) as $error) {
            $this->addFlash('error', $error->getMessage());
        }
    }
}
